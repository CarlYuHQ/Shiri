<?php

namespace Shiri\Domain\Calendar\Support;

use Carbon\CarbonImmutable;
use Shiri\Core\Db\Db;
use Shiri\Domain\Tickets\Repositories\Tickets as TicketsRepository;

/**
 * 例行计划（每周固定的安排）
 *
 * 只做两件事，故意保持简单：
 *   1. 存规则（每天 / 每周的哪几天，可选时间、开始日期）；
 *   2. 把「今天起 21 天内该出现的日子」**生成成真实待办**。
 *
 * 为什么落库成真 ticket 而不是虚拟显示：勾选、统计、逾期判断、日历打点全都复用现有逻辑，
 * 一行都不用改。代价是「改了规则，已经生成的旧条目不会自动消失」——
 * 用 zp_recurring_log 记 (规则, 日期) 唯一，保证同一规则同一天只生成一次，不会重复堆。
 */
class RecurringBoard
{
    /** 往后生成多少天 */
    private const HORIZON_DAYS = 21;

    public function __construct(
        private Db $db,
        private TicketsRepository $tickets
    ) {}

    /** 默认落到用户能访问的第一个项目（和待办页快速添加同一个口径） */
    private function defaultProjectId(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        try {
            foreach ((array) app(\Shiri\Domain\Projects\Services\Projects::class)->getProjectsUserHasAccessTo($userId) as $project) {
                $id = (int) ($project['id'] ?? 0);
                if ($id > 0) {
                    return $id;
                }
            }
        } catch (\Throwable) {
            return 0;
        }

        return 0;
    }

    /** 建表（本项目没有迁移体系，首次用到就建） */
    private function ensureSchema(): void
    {
        $c = $this->db->getConnection();

        $c->statement("
            CREATE TABLE IF NOT EXISTS zp_recurring (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                userId INT NOT NULL,
                projectId INT NOT NULL DEFAULT 0,
                title VARCHAR(200) NOT NULL,
                freq VARCHAR(10) NOT NULL DEFAULT 'weekly',
                weekdays VARCHAR(20) NOT NULL DEFAULT '',
                timeOfDay VARCHAR(5) NOT NULL DEFAULT '',
                startsOn DATE NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                createdAt DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_user (userId)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $c->statement("
            CREATE TABLE IF NOT EXISTS zp_recurring_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                ruleId INT UNSIGNED NOT NULL,
                dateKey DATE NOT NULL,
                ticketId INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_rule_date (ruleId, dateKey)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * 当前用户的全部规则
     *
     * @return array<int, array<string, mixed>>
     */
    public function rules(int $userId, bool $onlyActive = false): array
    {
        $this->ensureSchema();

        $query = $this->db->getConnection()->table('zp_recurring')
            ->where('userId', $userId)
            ->orderBy('id');

        if ($onlyActive) {
            $query->where('active', 1);
        }

        return $query->get()->map(fn ($row) => $this->hydrate((array) $row))->all();
    }

    /**
     * 新建规则
     *
     * @param  array{title?: string, freq?: string, weekdays?: array<int, int>|string, timeOfDay?: string, startsOn?: string}  $in
     * @return int 新规则 id，参数不合法时返回 0
     */
    public function create(int $userId, array $in): int
    {
        $this->ensureSchema();

        $title = trim(strip_tags((string) ($in['title'] ?? '')));
        if ($title === '') {
            return 0;
        }

        $freq = ($in['freq'] ?? 'weekly') === 'daily' ? 'daily' : 'weekly';

        // 星期用 1(一)~7(日)，去重排序；每周规则没勾任何一天就没意义
        $weekdays = $in['weekdays'] ?? [];
        $weekdays = is_array($weekdays) ? $weekdays : explode(',', (string) $weekdays);
        $weekdays = array_values(array_unique(array_filter(array_map('intval', $weekdays), fn ($d) => $d >= 1 && $d <= 7)));
        sort($weekdays);

        if ($freq === 'weekly' && $weekdays === []) {
            return 0;
        }

        $time = trim((string) ($in['timeOfDay'] ?? ''));
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $time = '';
        }

        $startsOn = trim((string) ($in['startsOn'] ?? ''));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $startsOn)) {
            $startsOn = dtHelper()->userNow()->format('Y-m-d');
        }

        return (int) $this->db->getConnection()->table('zp_recurring')->insertGetId([
            'userId' => $userId,
            'projectId' => $this->defaultProjectId($userId),
            'title' => mb_substr($title, 0, 200),
            'freq' => $freq,
            'weekdays' => implode(',', $weekdays),
            'timeOfDay' => $time,
            'startsOn' => $startsOn,
            'active' => 1,
            'createdAt' => dtHelper()->userNow()->formatDateTimeForDb(),
        ]);
    }

    public function delete(int $userId, int $ruleId): void
    {
        $this->ensureSchema();

        $c = $this->db->getConnection();
        $c->table('zp_recurring')->where('userId', $userId)->where('id', $ruleId)->delete();
        $c->table('zp_recurring_log')->where('ruleId', $ruleId)->delete();
    }

    public function toggle(int $userId, int $ruleId): void
    {
        $this->ensureSchema();

        $c = $this->db->getConnection();
        $row = $c->table('zp_recurring')->where('userId', $userId)->where('id', $ruleId)->first();
        if ($row === null) {
            return;
        }

        $c->table('zp_recurring')->where('id', $ruleId)->update(['active' => ((int) $row->active) === 1 ? 0 : 1]);
    }

    /**
     * 把未来 HORIZON_DAYS 天该出现的日子补成待办
     *
     * @return int 本次新建的条目数
     */
    public function sync(int $userId): int
    {
        $rules = $this->rules($userId, true);
        if ($rules === []) {
            return 0;
        }

        $now = dtHelper()->userNow();
        $today = CarbonImmutable::instance($now)->startOfDay();
        $c = $this->db->getConnection();
        $created = 0;

        foreach ($rules as $rule) {
            $startsOn = CarbonImmutable::parse($rule['startsOn'])->startOfDay();

            for ($i = 0; $i <= self::HORIZON_DAYS; $i++) {
                $day = $today->addDays($i);
                if ($day->lessThan($startsOn) || ! $this->matches($rule, $day)) {
                    continue;
                }

                $key = $day->format('Y-m-d');

                $exists = $c->table('zp_recurring_log')
                    ->where('ruleId', $rule['id'])
                    ->where('dateKey', $key)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $ticketId = $this->createTicket($userId, $rule, $day);
                if ($ticketId <= 0) {
                    continue;
                }

                $c->table('zp_recurring_log')->insert([
                    'ruleId' => $rule['id'],
                    'dateKey' => $key,
                    'ticketId' => $ticketId,
                ]);

                $created++;
            }
        }

        return $created;
    }

    /** 这条规则落在这一天吗 */
    private function matches(array $rule, CarbonImmutable $day): bool
    {
        if ($rule['freq'] === 'daily') {
            return true;
        }

        return in_array($day->dayOfWeekIso, $rule['weekdays'], true);
    }

    /** 真的建一条 ticket（直接走仓库，绕开 quickAddTicket 的邮件通知） */
    private function createTicket(int $userId, array $rule, CarbonImmutable $day): int
    {
        $projectId = (int) $rule['projectId'] ?: $this->defaultProjectId($userId);

        // 有时间的按用户时区换算成 UTC 存库；没时间的按「日期」原样存（和日历快速添加一致）
        if ($rule['timeOfDay'] !== '') {
            $due = dtHelper()->parseUserDateTime($day->format('Y-m-d').' '.$rule['timeOfDay'])
                ->setTimezone('UTC')
                ->formatDateTimeForDb();
        } else {
            $due = $day->format('Y-m-d').' 00:00:00';
        }

        // 默认「新建」状态：和 quickAddTicket 一样从项目状态标签里挑第一个 NEW 类型
        $status = 3;
        foreach ($this->tickets->getStateLabels($projectId) as $statusId => $config) {
            if (($config['statusType'] ?? '') === 'NEW') {
                $status = (int) $statusId;
                break;
            }
        }

        $ticketId = $this->tickets->addTicket([
            'headline' => $rule['title'],
            'type' => 'task',
            'description' => '由例行计划自动生成（'.$rule['summary'].'）',
            'date' => dtHelper()->dbNow()->formatDateTimeForDb(),
            'dateToFinish' => $due,
            'projectId' => $projectId,
            'status' => $status,
            'userId' => $userId,
            'editorId' => $userId,
            'tags' => 'recurring',
            'sprint' => '',
            'storypoints' => '',
            'priority' => '',
            'hourRemaining' => '',
            'planHours' => '',
            'acceptanceCriteria' => '',
            'editFrom' => $due,
            'editTo' => $due,
            'milestoneid' => '',
            'dependingTicketId' => '',
            'sortIndex' => '',
            'collaborators' => [],
        ]);

        return is_int($ticketId) ? $ticketId : (int) $ticketId;
    }

    /** 把表里的行整理成用起来舒服的数组 */
    private function hydrate(array $row): array
    {
        $weekdays = array_values(array_filter(array_map('intval', explode(',', (string) $row['weekdays'])), fn ($d) => $d >= 1 && $d <= 7));
        $freq = (string) $row['freq'];
        $time = (string) $row['timeOfDay'];

        return [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'freq' => $freq,
            'weekdays' => $weekdays,
            'timeOfDay' => $time,
            'startsOn' => (string) $row['startsOn'],
            'active' => ((int) $row['active']) === 1,
            'projectId' => (int) $row['projectId'],
            'summary' => self::summarize($freq, $weekdays, $time),
        ];
    }

    /**
     * 「每周一、三 09:00」这样的可读描述
     *
     * @param  array<int, int>  $weekdays
     */
    public static function summarize(string $freq, array $weekdays, string $time = ''): string
    {
        $names = [];
        foreach (range(1, 7) as $d) {
            $names[$d] = Lang::t('week.'.$d);
        }

        if ($freq === 'daily') {
            $text = Lang::t('plans.summary.daily');
        } elseif ($weekdays === array_keys($names)) {
            $text = Lang::t('plans.summary.weeklyAll');
        } else {
            $picked = array_map(fn ($d) => $names[$d] ?? '', $weekdays);
            $text = Lang::t('plans.summary.weekly', ['days' => implode(Lang::isEnglish() ? ', ' : '、', array_filter($picked))]);
        }

        return $time !== '' ? $text.' '.$time : $text;
    }
}
