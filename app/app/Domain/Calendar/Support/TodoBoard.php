<?php

namespace Shiri\Domain\Calendar\Support;

use Shiri\Domain\Tickets\Services\Tickets as TicketService;

/**
 * 「拾日」的待办数据层。
 *
 * 两处数据最终都会变成待办：
 *   1. ticket（zp_tickets，带 dateToFinish）—— 官方待办本体；
 *   2. 手记的日程（zp_calendar，带 dateFrom..dateTo）—— 由 {@see EventBoard} 提供。
 * 官方出口 TicketService::getOpenUserTicketsThisWeekAndLater() 已经把 ticket 按
 * overdue / thisWeek / later 分好桶并按截止日排序，但不给状态类型、不给「是否已完成」
 * 的判断，也不给适合直接展示的文案。
 *
 * 待办清单页要的是「所有还没做的事」，所以读 {@see self::items()}（ticket + 日程）；
 * 桌面小卡和统计页读 {@see self::todos()}（只有 ticket）——那两处已经各自把日程
 * 摊平算过一遍了，再合并会重复计数。
 */
class TodoBoard
{
    /** 同一次请求里只跑一次例行生成（多个页面部件会反复调 todos()） */
    private static bool $recurringSynced = false;

    public function __construct(
        private TicketService $tickets,
        private RecurringBoard $recurring,
        private EventBoard $events
    ) {}

    /**
     * 待办清单页用的完整清单：ticket + 手记的日程，混在一起按截止排好序。
     *
     * @return array<int, array<string, mixed>>
     */
    public function items(int $userId): array
    {
        $rows = $this->todos($userId);

        if ($userId > 0) {
            foreach ($this->eventRows($userId) as $row) {
                $rows[] = $row;
            }
        }

        $this->sortRows($rows);

        return $rows;
    }

    /**
     * 当前用户的全部待办（含已完成），已按「未完成在前 → 截止日早的在前 → 无截止日垫底」
     * 排好序，可直接丢给模板渲染。
     *
     * @return array<int, array<string, mixed>>
     */
    public function todos(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        // 先把例行计划该出现的那几天补成待办，再读数据（失败也不能拖垮页面）
        if (! self::$recurringSynced) {
            self::$recurringSynced = true;
            try {
                $this->recurring->sync($userId);
            } catch (\Throwable) {
                // 建表/写库失败就跳过，页面照常显示
            }
        }

        // includeDoneTickets = true：一次拿全（status='all'），已完成/未完成在下面自己标。
        $grouped = $this->tickets->getOpenUserTicketsThisWeekAndLater($userId, '', true);
        $labels = $this->tickets->getAllStatusLabelsByUserId($userId);

        $rows = [];
        foreach (['overdue', 'thisWeek', 'later'] as $bucket) {
            foreach (($grouped[$bucket]['tickets'] ?? []) as $row) {
                $rows[] = $row;
            }
        }

        $now = dtHelper()->userNow();
        $todayStart = $now->startOfDay();
        $weekEnd = $now->endOfWeek(\Carbon\CarbonInterface::SUNDAY)->endOfDay();

        $todos = [];
        foreach ($rows as $row) {
            $projectId = (int) ($row['projectId'] ?? 0);
            $status = (string) ($row['status'] ?? '');

            $statusType = (string) ($labels[$projectId][$status]['statusType'] ?? '');
            $statusTitle = (string) ($labels[$projectId][$status]['title'] ?? '');
            $statusColor = (string) ($labels[$projectId][$status]['color'] ?? '');

            $due = $this->parseDue($row['dateToFinish'] ?? null);

            $isDone = $statusType === 'DONE';
            $isOverdue = ! $isDone && $due !== null && $due->lt($todayStart);
            $isToday = $due !== null && $due->isSameDay($now);
            $isThisWeek = $due !== null && $due->between($todayStart, $weekEnd);

            $todos[] = [
                'id' => (int) ($row['id'] ?? 0),
                // 清单里混着 ticket 和日程两种来源，前端勾选要按这个分发到不同接口
                'source' => 'ticket',
                'title' => $this->cleanTitle($row['headline'] ?? ''),
                // 桌面浮层的悬浮详情卡要显示待办正文；富文本描述先去标签再压空白
                'description' => trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($row['description'] ?? '')))),
                'projectId' => $projectId,
                'statusLabel' => $statusTitle,
                'statusColor' => $statusColor,
                'done' => $isDone,
                'overdue' => $isOverdue,
                'today' => $isToday,
                'bucket' => match (true) {
                    $isDone => 'done',
                    $due === null => 'none',
                    $isOverdue => 'overdue',
                    $isToday => 'today',
                    $isThisWeek => 'week',
                    default => 'later',
                },
                // 取消勾选后该回到哪一组。已完成的条目 bucket 恒为 done，但重新打开时
                // 要按截止日回到逾期/今天/本周/以后，前端靠这个字段搬节点。
                'openBucket' => match (true) {
                    $due === null => 'none',
                    $due->lt($todayStart) => 'overdue',
                    $isToday => 'today',
                    $isThisWeek => 'week',
                    default => 'later',
                },
                'due' => $due,
                'dueIso' => $due?->format('Y-m-d\TH:i'),
                'dueDay' => $due?->format('Y-m-d'),
                'dueLabel' => $this->dueLabel($due, $now),
                'hasTime' => $due !== null && $due->format('H:i') !== '00:00',
                'modified' => $this->parseDue($row['modified'] ?? null),
            ];
        }

        $this->sortRows($todos);

        return $todos;
    }

    /**
     * 把 zp_calendar 的日程摊成待办行。
     *
     * 与 ticket 的差别只有两点：
     *  - 截止取**结束**那天：跨天任务在它还在进行时不该算逾期；
     *  - 没有 projectId / 状态色，勾选走 /calendar/toggleEventDone（改 done 列，
     *    不删数据——日程在日历上还要留着）。
     *
     * @return array<int, array<string, mixed>>
     */
    private function eventRows(int $userId): array
    {
        $now = dtHelper()->userNow();
        $todayStart = $now->startOfDay();
        $weekEnd = $now->endOfWeek(\Carbon\CarbonInterface::SUNDAY)->endOfDay();

        $rows = [];
        foreach ($this->events->events($userId) as $event) {
            /** @var \Carbon\CarbonImmutable $start */
            $start = $event['start'];
            /** @var \Carbon\CarbonImmutable $end */
            $end = $event['end'];
            $isDone = (bool) ($event['done'] ?? false);

            $due = $end;
            $spansDays = (int) $start->startOfDay()->diffInDays($end->startOfDay());

            $isToday = $due->isSameDay($now);
            $isOverdue = ! $isDone && $due->lt($todayStart);
            $isThisWeek = $due->between($todayStart, $weekEnd);

            // 全天日程不显示时刻；跨天的只显示「至 X 日」，免得被误读成每天都有那个点
            $withTime = ! $event['allDay'] && $spansDays === 0;
            // 单天日程显示**开始**时刻（人关心的是几点开始），分桶/排序仍按结束时间
            $labelAt = $spansDays === 0 ? $start : $end;

            $rows[] = [
                'id' => $event['id'],
                'source' => 'event',
                'title' => (string) $event['title'],
                // 日程没有独立正文：再把标题写一遍只会让悬浮卡重复显示
                'description' => '',
                'projectId' => 0,
                'statusLabel' => '',
                'statusColor' => '',
                'done' => $isDone,
                'overdue' => $isOverdue,
                'today' => $isToday,
                'bucket' => match (true) {
                    $isDone => 'done',
                    $isOverdue => 'overdue',
                    $isToday => 'today',
                    $isThisWeek => 'week',
                    default => 'later',
                },
                'openBucket' => match (true) {
                    $isOverdue => 'overdue',
                    $isToday => 'today',
                    $isThisWeek => 'week',
                    default => 'later',
                },
                'due' => $due,
                'dueIso' => $due->format('Y-m-d\TH:i'),
                'dueDay' => $due->format('Y-m-d'),
                'dueLabel' => $spansDays > 0
                    ? Lang::t('due.until', ['date' => $this->dayLabel($end, $now)])
                    : $this->dueLabel($labelAt, $now, $withTime),
                'hasTime' => $withTime,
                // 行首的小圆点用日程自己选的颜色，和日历上是同一个色
                'color' => (string) ($event['color'] ?? ''),
                'modified' => null,
            ];
        }

        return $rows;
    }

    /**
     * 未完成在前 → 截止早的在前 → 无截止日垫底。
     *
     * @param  array<int, array<string, mixed>>  $todos
     */
    private function sortRows(array &$todos): void
    {
        usort($todos, function (array $a, array $b): int {
            // 已完成的沉到最底，再按完成时间新→旧
            if ($a['done'] !== $b['done']) {
                return $a['done'] ? 1 : -1;
            }
            if ($a['done'] && $b['done']) {
                return ($b['modified']?->getTimestamp() ?? 0) <=> ($a['modified']?->getTimestamp() ?? 0);
            }
            // 未完成：有截止日的在前，早的在前
            if (($a['due'] === null) !== ($b['due'] === null)) {
                return $a['due'] === null ? 1 : -1;
            }
            if ($a['due'] !== null && $b['due'] !== null) {
                return $a['due']->getTimestamp() <=> $b['due']->getTimestamp();
            }
            // 都没截止日：新建的在前
            return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
        });
    }

    /**
     * 统计页与桌面小卡用的汇总数字。
     *
     * 说明：Shiri 没有「完成时间」这一列，ticket 被标记完成时 status 变了、modified
     * 跟着变，但之后的任何编辑也会改 modified。所以「本周/本月完成」按「当前是已完成
     * 状态 且 最后更新在区间内」统计，个人使用足够准，界面上也如实标注了口径。
     *
     * @return array<string, mixed>
     */
    public function stats(int $userId): array
    {
        $todos = $this->todos($userId);
        $now = dtHelper()->userNow();

        $weekStart = $now->startOfWeek(\Carbon\CarbonInterface::MONDAY)->startOfDay();
        $monthStart = $now->startOfMonth();
        $weekEnd = $now->endOfWeek(\Carbon\CarbonInterface::SUNDAY)->endOfDay();

        $open = 0;
        $done = 0;
        $overdue = 0;
        $today = 0;
        $thisWeek = 0;
        $undated = 0;
        $doneThisWeek = 0;
        $doneThisMonth = 0;
        $doneToday = 0;

        // 近 7 天完成数（含今天），key = Y-m-d
        $recent = [];
        for ($i = 6; $i >= 0; $i--) {
            $recent[$now->subDays($i)->format('Y-m-d')] = 0;
        }

        $oldestOpen = null;

        foreach ($todos as $todo) {
            /** @var \Carbon\CarbonImmutable|null $due */
            $due = $todo['due'];
            /** @var \Carbon\CarbonImmutable|null $modified */
            $modified = $todo['modified'];

            if ($todo['done']) {
                $done++;
                if ($modified !== null) {
                    if ($modified->gte($weekStart) && $modified->lte($weekEnd)) {
                        $doneThisWeek++;
                    }
                    if ($modified->gte($monthStart)) {
                        $doneThisMonth++;
                    }
                    if ($modified->isSameDay($now)) {
                        $doneToday++;
                    }
                    $day = $modified->format('Y-m-d');
                    if (array_key_exists($day, $recent)) {
                        $recent[$day]++;
                    }
                }

                continue;
            }

            $open++;
            if ($todo['overdue']) {
                $overdue++;
            }
            if ($todo['today']) {
                $today++;
            }
            if ($due === null) {
                $undated++;
            } elseif ($due->between($weekStart, $weekEnd)) {
                $thisWeek++;
            }

            if ($oldestOpen === null || ($due !== null && ($oldestOpen['due'] === null || $due->lt($oldestOpen['due'])))) {
                $oldestOpen = $todo;
            }
        }

        return [
            'open' => $open,
            'done' => $done,
            'overdue' => $overdue,
            'today' => $today,
            'thisWeek' => $thisWeek,
            'undated' => $undated,
            'doneToday' => $doneToday,
            'doneThisWeek' => $doneThisWeek,
            'doneThisMonth' => $doneThisMonth,
            'recent' => $recent,
            'recentMax' => max(1, ...array_values($recent)),
            'oldestOpen' => $oldestOpen,
            'total' => $open + $done,
        ];
    }

    /**
     * 分组标题：按展示顺序给出 bucket => 中文标题。
     *
     * 与 todos() 里写进每行的 'bucket' 一一对应，模板和前端脚本都从这里取，
     * 避免中文标题散落在三处。
     *
     * @return array<string, string>
     */
    public static function bucketLabels(): array
    {
        return [
            'overdue' => Lang::t('bucket.overdue'),
            'today' => Lang::t('bucket.today'),
            'week' => Lang::t('bucket.week'),
            'later' => Lang::t('bucket.later'),
            'none' => Lang::t('bucket.none'),
            'done' => Lang::t('bucket.done'),
        ];
    }

    /**
     * 按 bucket 分组，只返回非空组，顺序与 bucketLabels() 一致。
     *
     * @param  array<int, array<string, mixed>>  $todos
     * @return array<string, array{todos: array<int, array<string, mixed>>}>
     */
    public function group(array $todos): array
    {
        $grouped = [];
        foreach (array_keys(self::bucketLabels()) as $bucket) {
            $slice = array_values(array_filter($todos, fn ($t) => ($t['bucket'] ?? '') === $bucket));
            if ($slice !== []) {
                $grouped[$bucket] = ['todos' => $slice];
            }
        }

        return $grouped;
    }

    /**
     * 新建待办要落到哪个项目。
     *
     * 「拾日」里没有项目概念，但 ticket 必须有 projectId，所以取用户能访问的
     * 第一个项目。没有项目时返回 0，前端据此禁用输入框。
     */
    public function defaultProjectId(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        try {
            $projects = app(\Shiri\Domain\Projects\Services\Projects::class)->getProjectsUserHasAccessTo($userId);
        } catch (\Throwable) {
            return 0;
        }

        foreach ((array) $projects as $project) {
            $id = (int) ($project['id'] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    // ── 内部 ────────────────────────────────────────────────────────────────

    /**
     * Shiri 用 0000-00-00 / 1969-12-31 当「空日期」哨兵值，直接 parse 会得到
     * 无意义的极早时间，这里统一挡掉。
     *
     * 注意：dtHelper()->parseDbDateTime() 返回的是**数据库时区（UTC）**的实例，
     * 直接拿它和 dtHelper()->userNow()（用户时区）做 format()/isSameDay() 会错开
     * 8 小时——例如库里 2026-11-05 16:00Z（= 北京时间 11-06 00:00）会被判成昨天。
     * 所以这里统一转成用户时区再交出去。
     */
    private function parseDue(?string $raw): ?\Carbon\CarbonImmutable
    {
        $raw = trim((string) $raw);
        if ($raw === '' || str_starts_with($raw, '0000-00-00') || str_starts_with($raw, '1969-12-31')) {
            return null;
        }

        try {
            $utc = dtHelper()->parseDbDateTime($raw);

            return $utc->setTimezone(dtHelper()->userNow()->getTimezone());
        } catch (\Throwable) {
            return null;
        }
    }

    private function cleanTitle(mixed $headline): string
    {
        $title = trim(html_entity_decode(strip_tags((string) $headline), ENT_QUOTES, 'UTF-8'));

        return $title === '' ? '未命名待办' : $title;
    }

    /**
     * 人读的截止文案：今天 / 明天 / 昨天 / 3 天前 / 11月25日，需要时带上时间。
     *
     * @param  bool  $withTime  false 用于全天日程：库里存着 09:00，但那不是用户填的
     */
    private function dueLabel(?\Carbon\CarbonImmutable $due, \Carbon\CarbonImmutable $now, bool $withTime = true): string
    {
        if ($due === null) {
            return Lang::t('due.none');
        }

        $days = (int) $now->startOfDay()->diffInDays($due->startOfDay(), false);
        $time = ! $withTime || $due->format('H:i') === '00:00' ? '' : ' '.$due->format('H:i');

        return match (true) {
            $days === 0 => Lang::t('due.today').$time,
            $days === 1 => Lang::t('due.tomorrow').$time,
            $days === 2 => Lang::t('due.dayAfter').$time,
            $days === -1 => Lang::t('due.yesterday').$time,
            $days < -1 && $days > -8 => Lang::t('due.daysAgo', ['n' => abs($days)]),
            default => $this->dayLabel($due, $now).$time,
        };
    }

    /** 只有日期，不带「今天 / 明天」这类相对说法 */
    private function dayLabel(\Carbon\CarbonImmutable $day, \Carbon\CarbonImmutable $now): string
    {
        if (Lang::isEnglish()) {
            return $day->year === $now->year ? $day->format('M j') : $day->format('M j, Y');
        }

        return $day->year === $now->year ? $day->format('n月j日') : $day->format('Y年n月j日');
    }
}
