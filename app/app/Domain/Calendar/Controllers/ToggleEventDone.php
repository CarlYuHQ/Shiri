<?php

namespace Shiri\Domain\Calendar\Controllers;

use Shiri\Core\Auth\Permissions\RequiresPermission;
use Shiri\Core\Controller\Controller;
use Shiri\Domain\Calendar\Permissions\CalendarPermissions;
use Shiri\Domain\Calendar\Support\EventBoard;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * 勾选 / 取消勾选一条日程 /calendar/toggleEventDone（POST）
 *
 * 待办清单里的日程行和 ticket 行长得一样，但数据不是一个东西：ticket 走
 * Tickets.Tickets.markTicketDone，日程只能在这里改 zp_calendar.done。
 * 只标记不删除——日程在日历上还要留着，划掉就够了。
 *
 * 返回 {ok:true}，由 shiri-board.js 就地挪分组，不刷新页面。
 */
class ToggleEventDone extends Controller
{
    private EventBoard $events;

    public function init(EventBoard $events): void
    {
        $this->events = $events;
    }

    #[RequiresPermission(CalendarPermissions::EDIT)]
    public function post(array $params): Response
    {
        $userId = (int) session('userdata.id');
        $id = (int) ($params['id'] ?? 0);
        // 前端只发 0/1，但手打字串也不该被当成「完成」
        $done = filter_var($params['done'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

        if ($id <= 0) {
            return new JsonResponse(['ok' => false, 'error' => 'missing_id'], 400);
        }

        return new JsonResponse(['ok' => $this->events->setDone($id, $userId, $done)]);
    }
}
