<?php

namespace Shiri\Domain\Calendar\Support;

use Carbon\CarbonImmutable;
use Shiri\Core\Db\Db as DbCore;
use Shiri\Domain\Calendar\Services\Calendar as CalendarService;

/**
 * 「日程」这一侧的待办数据。
 *
 * 拾日里有两份数据会在日历上出现：
 *   1. ticket（zp_tickets，带 dateToFinish）—— 待办清单页原本只读这一份；
 *   2. 手记的日程（zp_calendar，带 dateFrom..dateTo、颜色、全天）—— 只在日历上。
 *
 * 用户点「新增日程」记的是第 2 种，所以待办清单里一条都看不到。本类把第 2 种
 * 整理成和 ticket 同一副形状，交给 {@see TodoBoard::items()} 合并，两页从此
 * 读的是同一份东西。
 *
 * zp_calendar 原本没有「完成」这个概念（没有 done 列），这里按需补上：
 * 本项目没有迁移体系，第一次用到时检查 information_schema 再 ALTER。
 */
class EventBoard
{
    /** 同一次请求里只检查一次表结构 */
    private static bool $schemaReady = false;

    /** 已完成日程在待办页里往回翻多久（再久远的都在日历上，不必挤进清单） */
    private const DONE_LOOKBACK_DAYS = 30;

    public function __construct(private DbCore $db) {}

    /**
     * 当前用户的日程，已换成用户时区、按开始时间升序。
     *
     * 未完成的全给（哪怕早就过期，它仍然是「还欠着的事」）；已完成的只给最近
     * DONE_LOOKBACK_DAYS 天内的，免得清单越用越长。
     *
     * @return array<int, array<string, mixed>>
     */
    public function events(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $this->ensureSchema();

        $rows = $this->db->getConnection()
            ->table('zp_calendar')
            ->where('userId', $userId)
            ->whereNotNull('dateFrom')
            ->orderBy('dateFrom')
            ->get();

        $tz = dtHelper()->userNow()->getTimezone();
        $doneSince = dtHelper()->userNow()->subDays(self::DONE_LOOKBACK_DAYS)->startOfDay();

        $out = [];
        foreach ($rows as $row) {
            $row = (array) $row;

            // 日期残缺的行（早期日期控件坏了存进 NULL / 零值）直接跳过
            if (! dtHelper()->isValidDateString((string) ($row['dateFrom'] ?? ''))) {
                continue;
            }

            try {
                $start = dtHelper()->parseDbDateTime($row['dateFrom'])->setTimezone($tz);
                $end = dtHelper()->isValidDateString((string) ($row['dateTo'] ?? ''))
                    ? dtHelper()->parseDbDateTime($row['dateTo'])->setTimezone($tz)
                    : $start;
            } catch (\Throwable) {
                continue;
            }

            // 结束早于开始（手误）按单天算，别出现负区间
            if ($end->lt($start)) {
                $end = $start;
            }

            $done = (bool) ($row['done'] ?? false);
            if ($done && $end->lt($doneSince)) {
                continue;
            }

            $title = trim(html_entity_decode(strip_tags((string) ($row['description'] ?? '')), ENT_QUOTES, 'UTF-8'));
            $kind = (string) ($row['kind'] ?? '');

            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => $title === '' ? Lang::t('todos.untitledEvent') : $title,
                'start' => $start,
                'end' => $end,
                'allDay' => filter_var($row['allDay'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                'kind' => $kind,
                // 和日历上同色：调色板是 Calendar 服务的常量，两边别各存一份
                'color' => CalendarService::EVENT_COLORS[$kind]['hex'] ?? '',
                'done' => $done,
            ];
        }

        return $out;
    }

    /**
     * 勾选 / 取消勾选一条日程。
     *
     * 只改 done，不删数据：日程在日历上还要留着，划掉就够了。
     */
    public function setDone(int $eventId, int $userId, bool $done): bool
    {
        if ($eventId <= 0 || $userId <= 0) {
            return false;
        }

        $this->ensureSchema();

        $affected = $this->db->getConnection()
            ->table('zp_calendar')
            ->where('id', $eventId)
            ->where('userId', $userId)
            ->update(['done' => $done ? 1 : 0]);

        return $affected !== false;
    }

    /** zp_calendar 加 done 列（没有迁移体系，用前先探一次） */
    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        self::$schemaReady = true;

        try {
            $c = $this->db->getConnection();
            if ($c->selectOne("SHOW COLUMNS FROM zp_calendar LIKE 'done'") === null) {
                $c->statement('ALTER TABLE zp_calendar ADD COLUMN done TINYINT(1) NOT NULL DEFAULT 0');
            }
        } catch (\Throwable) {
            // 建不出来也不该让页面挂掉：读的时候按 0 处理
        }
    }
}
