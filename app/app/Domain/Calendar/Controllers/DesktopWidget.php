<?php

namespace Shiri\Domain\Calendar\Controllers;

use Shiri\Core\Auth\Permissions\RequiresPermission;
use Shiri\Core\Controller\Controller;
use Shiri\Domain\Calendar\Permissions\CalendarPermissions;
use Shiri\Domain\Calendar\Services\Calendar as CalendarService;
use Shiri\Domain\Calendar\Support\CardTheme;
use Shiri\Domain\Calendar\Support\Lang;
use Shiri\Domain\Calendar\Support\Prefs;
use Shiri\Domain\Calendar\Support\TodoBoard;
use Shiri\Domain\Calendar\Services\Milestones;
use Symfony\Component\HttpFoundation\Response;

/**
 * 桌面浮层小卡 /calendar/desktopWidget
 *
 * 一张贴在桌面上的迷你月历：上面是当月网格（有安排的日子打点、今天实心），
 * 下面是今天要做的几件事。用独立的 widget 布局，不带顶栏侧栏，整页只有一张卡。
 */
class DesktopWidget extends Controller
{
    private TodoBoard $board;

    private CalendarService $calendar;

    private Prefs $prefs;

    private Milestones $milestones;

    public function init(TodoBoard $board, CalendarService $calendar, Prefs $prefs, Milestones $milestones): void
    {
        $this->board = $board;
        $this->calendar = $calendar;
        $this->prefs = $prefs;
        $this->milestones = $milestones;
    }

    #[RequiresPermission(CalendarPermissions::VIEW)]
    public function get(array $params): Response
    {
        // 先把语言偏好同步给 Shiri 本体，模板里的 __() 才会跟着切
        Lang::applyToShiri();
        $userId = (int) session('userdata.id');
        $now = dtHelper()->userNow();
        $todayKey = $now->format('Y-m-d');

        $monthStart = $now->startOfMonth();
        $monthEnd = $now->endOfMonth();

        $todos = $this->board->todos($userId);

        // 「哪一天有安排」：未完成的待办按截止日聚合（已完成的不算负担）。
        // dots 只留数量给窄屏用圆点，dayItems 带标题给大卡的日程条用。
        $dots = [];
        $dayItems = [];
        $todayItems = [];
        $overdueCount = 0;

        foreach ($todos as $todo) {
            if ($todo['done']) {
                continue;
            }

            if ($todo['overdue']) {
                $overdueCount++;
            }

            if ($todo['dueDay'] === null) {
                continue;
            }

            $dots[$todo['dueDay']] = ($dots[$todo['dueDay']] ?? 0) + 1;
            $dayItems[$todo['dueDay']][] = [
                'time' => $todo['hasTime'] ? $todo['due']->format('H:i') : '',
                'title' => $todo['title'],
                'description' => $todo['description'],
            ];

            if ($todo['today']) {
                $todayItems[] = [
                    'id' => $todo['id'],
                    'title' => $todo['title'],
                    'time' => $todo['hasTime'] ? $todo['due']->format('H:i') : '',
                    'description' => $todo['description'],
                    'openBucket' => $todo['openBucket'],
                ];
            }
        }

        // 日历事件（ticket 已经在上面按待办算过，这里只要 calendar 类型）
        try {
            $events = $this->calendar->getCalendar($userId, $monthStart->startOfDay(), $monthEnd->endOfDay());
        } catch (\Throwable) {
            $events = [];
        }

        foreach ($events as $event) {
            if (($event['eventType'] ?? '') !== 'calendar') {
                continue;
            }

            // 在待办页勾掉的日程不再算进「今天要做的事」，也不算打点（和已完成 ticket 一致）
            if (! empty($event['done'])) {
                continue;
            }

            $startDay = $this->toUserDay($event['dateFrom'] ?? '');
            if ($startDay === null) {
                continue;
            }

            // 跨天日程要在区间里**每一天**都出现，而不是只在开始日。
            // 结束日缺省或早于开始日时按单天处理（dateTo 是包含式：15 号到 15 号就是一天）。
            $endDay = $this->toUserDay($event['dateTo'] ?? '') ?? $startDay;
            if (strcmp($endDay, $startDay) < 0) {
                $endDay = $startDay;
            }

            // 只画落进本月窗口的那一段：上个月开始、这个月还在继续的要能接上
            $rangeStart = max($startDay, $monthStart->format('Y-m-d'));
            $rangeEnd = min($endDay, $monthEnd->format('Y-m-d'));
            if (strcmp($rangeStart, $rangeEnd) > 0) {
                continue;
            }

            $eventTitle = trim(strip_tags((string) ($event['title'] ?? ''))) ?: '未命名日程';
            $eventDesc = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($event['description'] ?? ''))));
            // 事件自选颜色（主窗口里就是这个色），带进 chip 好让两处看起来是同一个
            $eventColor = (string) (CalendarService::EVENT_COLORS[(string) ($event['colorKind'] ?? '')]['hex'] ?? '');
            // 时间只标在开始那天，续下去的日期只留标题，否则每天重复一个起始时刻会误读成「每天都 09:30 开始」
            $startTime = $this->toUserTime($event['dateFrom'] ?? '');
            $reachesToday = strcmp($todayKey, $rangeStart) >= 0 && strcmp($todayKey, $rangeEnd) <= 0;

            if ($reachesToday) {
                $todayItems[] = [
                    'id' => 0,
                    'title' => $eventTitle,
                    'time' => $startTime,
                    'description' => $eventDesc,
                    'openBucket' => 'today',
                    'color' => $eventColor,
                ];
            }

            $cursor = new \DateTimeImmutable($rangeStart);
            $stop = new \DateTimeImmutable($rangeEnd);
            while ($cursor <= $stop) {
                $day = $cursor->format('Y-m-d');

                $dots[$day] = ($dots[$day] ?? 0) + 1;
                $dayItems[$day][] = [
                    'time' => $day === $startDay ? $startTime : '',
                    'title' => $eventTitle,
                    'description' => $eventDesc,
                    'color' => $eventColor,
                ];

                $cursor = $cursor->modify('+1 day');
            }
        }

        // 有时间的排前面（按时间），全天的待办排最后
        usort($todayItems, function (array $a, array $b): int {
            if (($a['time'] === '') !== ($b['time'] === '')) {
                return $a['time'] === '' ? 1 : -1;
            }

            return strcmp($a['time'], $b['time']);
        });

        $prefs = $this->prefs->all($userId);

        // 右侧栏：和主窗口一样的三组节点（近期 / 遥远未来 / 已过去）
        // 底部横条是窄卡退化形态，只留未来最近的三个
        $msView = $this->milestones->view();
        $msItems = array_values(array_filter(
            $msView['all'],
            fn (array $m): bool => $m['bucket'] !== 'past'
        ));
        $this->tpl->assign('msGroups', $msView['groups']);
        $this->tpl->assign('msCount', $msView['count']);
        $this->tpl->assign('msItems', array_slice($msItems, 0, 3));

        $this->tpl->assign('grid', $this->buildGrid($monthStart, $monthEnd, $now, $dots, $dayItems, $prefs[Prefs::WEEK_START]));
        // 卡片默认 1040×790，今天这块放 5 行；再多就折叠成「还有 N 件」
        $this->tpl->assign('todayItems', array_slice($todayItems, 0, 5));
        $this->tpl->assign('moreCount', max(0, count($todayItems) - 5));
        $this->tpl->assign('todayCount', count($todayItems));
        $this->tpl->assign('overdueCount', $overdueCount);
        // 中文「2026 年 11 月」是四个片段拼的，英文按月名写成一整串（November 2026）
        $this->tpl->assign('isEn', Lang::isEnglish());
        $this->tpl->assign('monthTitle', $now->format('F').' '.$now->format('Y'));
        $this->tpl->assign('monthLabel', $now->format('n'));
        $this->tpl->assign('yearLabel', $now->format('Y'));
        $this->tpl->assign('today', $now);

        // 时段主题：设置页里可以固定某一档，默认跟着钟点走
        $theme = CardTheme::resolve($prefs[Prefs::THEME], $now);
        $this->tpl->assign('theme', $theme['key']);
        $this->tpl->assign('themeLabel', $theme['label']);
        $this->tpl->assign('themeIcon', $theme['icon']);
        $this->tpl->assign('themeMode', $prefs[Prefs::THEME]);
        $this->tpl->assign('cardAlpha', (float) $prefs[Prefs::CARD_ALPHA]);
        $this->tpl->assign('weekStart', $prefs[Prefs::WEEK_START]);
        // 「今天 · 11月7日 周六」里的日期部分，英文下换成 Nov 7, Sat
        $this->tpl->assign('todayLabel', Lang::isEnglish()
            ? $now->format('M j, D')
            : $now->format('n月j日').' 周'.['日', '一', '二', '三', '四', '五', '六'][$now->dayOfWeek]);

        // 浮层上的静态文案（语言跟随设置）
        $this->tpl->assign('lt', [
            'today' => Lang::t('widget.today'),
            'add' => Lang::t('widget.add'),
            'open' => Lang::t('widget.open'),
            'opacity' => Lang::t('widget.opacity'),
            'empty' => Lang::t('widget.empty'),
            'more' => Lang::t('widget.more'),
            'overdue' => Lang::t('widget.overdue'),
            'markDone' => Lang::t('todos.markDone'),
            'msTitle' => Lang::t('ms.title'),
            'msUnit' => Lang::t('ms.unit'),
            'msToday' => Lang::t('ms.today'),
            'msEmpty' => Lang::t('ms.empty'),
            'msSoon' => Lang::t('ms.soon'),
            'msFar' => Lang::t('ms.far'),
            'msPast' => Lang::t('ms.past'),
        ]);
        $this->tpl->assign('defaultProjectId', $this->board->defaultProjectId($userId));

        return $this->tpl->display('calendar.desktopWidget', 'widget');
    }

    /**
     * 当月网格：周一起始，补齐前后月的日期，按 7 个一组切好。
     *
     * @param  array<string, int>  $dots  每天的安排数（窄屏打点用）
     * @param  array<string, array<int, array{time: string, title: string}>>  $dayItems  每天的具体条目（大卡列条用）
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildGrid($monthStart, $monthEnd, $now, array $dots, array $dayItems = [], string $weekStart = 'sun'): array
    {
        // 一周从周几开始可以在设置里改（默认周日，和 macOS 日历一致）
        $monday = $weekStart === 'mon';
        $cursor = $monthStart->startOfWeek($monday ? \Carbon\CarbonInterface::MONDAY : \Carbon\CarbonInterface::SUNDAY);
        $last = $monthEnd->endOfWeek($monday ? \Carbon\CarbonInterface::SUNDAY : \Carbon\CarbonInterface::SATURDAY);
        $todayKey = $now->format('Y-m-d');

        $weeks = [];
        $week = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $key = $cursor->format('Y-m-d');

            $items = $dayItems[$key] ?? [];

            $week[] = [
                'day' => (int) $cursor->format('j'),
                'key' => $key,
                'inMonth' => $cursor->month === $monthStart->month,
                'isToday' => $key === $todayKey,
                'weekend' => $cursor->isWeekend(),
                'dots' => min(3, $dots[$key] ?? 0),
                // 大卡每个格子最多放 2 条，多出来的折成「+N」
                'items' => array_slice($items, 0, 2),
                'more' => max(0, count($items) - 2),
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }

            $cursor = $cursor->addDay();
        }

        if ($week !== []) {
            $weeks[] = $week;
        }

        return $weeks;
    }

    /** DB 里的 UTC 时间 → 用户时区的日期键（Y-m-d） */
    private function toUserDay(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '' || str_starts_with($raw, '0000-00-00') || str_starts_with($raw, '1969-12-31')) {
            return null;
        }

        try {
            return dtHelper()->parseDbDateTime($raw)
                ->setTimezone(dtHelper()->userNow()->getTimezone())
                ->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function toUserTime(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return '';
        }

        try {
            $time = dtHelper()->parseDbDateTime($raw)
                ->setTimezone(dtHelper()->userNow()->getTimezone());

            return $time->format('H:i') === '00:00' ? '' : $time->format('H:i');
        } catch (\Throwable) {
            return '';
        }
    }
}
