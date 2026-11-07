<?php

namespace Shiri\Domain\Calendar\Controllers;

use Shiri\Core\Auth\Permissions\RequiresPermission;
use Shiri\Core\Controller\Controller;
use Shiri\Domain\Calendar\Support\Lang;
use Shiri\Domain\Calendar\Permissions\CalendarPermissions;
use Shiri\Domain\Calendar\Support\TodoBoard;
use Symfony\Component\HttpFoundation\Response;

/**
 * 待办清单页 /calendar/showTodos
 *
 * 日历回答「这件事排在哪天」，这一页回答「还有哪些事没做」。两者读的是同一份
 * 数据（分配给自己的 ticket），只是切法不同，取数统一交给 TodoBoard。
 */
class ShowTodos extends Controller
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

        $todos = $this->board->todos($userId);

        $open = array_values(array_filter($todos, fn ($t) => ! $t['done']));
        $done = array_values(array_filter($todos, fn ($t) => $t['done']));

        $this->tpl->assign('todos', $todos);
        $this->tpl->assign('openTodos', $open);
        $this->tpl->assign('doneTodos', $done);
        $this->tpl->assign('groups', $this->board->group($todos));
        $this->tpl->assign('bucketLabels', TodoBoard::bucketLabels());
        $this->tpl->assign('defaultProjectId', $this->board->defaultProjectId($userId));
        $this->tpl->assign('openCount', count($open));
        $this->tpl->assign('doneCount', count($done));
        $this->tpl->assign('overdueCount', count(array_filter($open, fn ($t) => $t['overdue'])));

        session(['lastPage' => BASE_URL.'/calendar/showTodos/']);

        return $this->tpl->display('calendar.showTodos');
    }
}
