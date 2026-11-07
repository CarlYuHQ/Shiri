<?php

namespace Shiri\Domain\Calendar\Controllers;

use Shiri\Core\Auth\Permissions\RequiresPermission;
use Shiri\Core\Controller\Controller;
use Shiri\Domain\Calendar\Support\Lang;
use Shiri\Domain\Calendar\Permissions\CalendarPermissions;
use Shiri\Domain\Calendar\Support\TodoBoard;
use Symfony\Component\HttpFoundation\Response;

/**
 * 统计页 /calendar/showStats
 *
 * 只给数字和一条最小的趋势条，不做图表库。核心是回答三个问题：
 * 现在积压多少、这周做了多少、逾期多久了。
 */
class ShowStats extends Controller
{
    private TodoBoard $board;

    public function init(TodoBoard $board): void
    {
        $this->board = $board;
    }

    #[RequiresPermission(CalendarPermissions::VIEW)]
    public function get(array $params): Response
    {
        // 先把语言偏好同步给 Shiri 本体，模板里的 __() 才会跟着切
        Lang::applyToShiri();
        $userId = (int) session('userdata.id');

        $this->tpl->assign('stats', $this->board->stats($userId));
        $this->tpl->assign('todos', $this->board->todos($userId));

        session(['lastPage' => BASE_URL.'/calendar/showStats/']);

        return $this->tpl->display('calendar.showStats');
    }
}
