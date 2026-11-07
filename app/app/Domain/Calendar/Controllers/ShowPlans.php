<?php

namespace Shiri\Domain\Calendar\Controllers;

use Shiri\Core\Auth\Permissions\RequiresPermission;
use Shiri\Core\Controller\Controller;
use Shiri\Domain\Calendar\Permissions\CalendarPermissions;
use Shiri\Domain\Calendar\Support\Lang;
use Shiri\Domain\Calendar\Support\RecurringBoard;
use Symfony\Component\HttpFoundation\Response;

/**
 * 例行计划 /calendar/showPlans
 *
 * 「每周三 09:00 组会」这种固定安排。页面只做三件事：看列表、加一条、删/停用。
 * 真正的生成在 RecurringBoard::sync()，每个打开待办相关页面的人都会触发一次补全，
 * 所以不需要常驻的定时任务。
 */
class ShowPlans extends Controller
{
    private RecurringBoard $board;

    public function init(RecurringBoard $board): void
    {
        $this->board = $board;
    }

    #[RequiresPermission(CalendarPermissions::VIEW)]
    public function get(array $params): Response
    {
        // 先把语言偏好同步给 Shiri 本体，模板里的 __() 才会跟着切
        Lang::applyToShiri();
        $userId = (int) session('userdata.id');

        // 进页面先补一次：把未来三周内该出现的日子生成成待办
        try {
            $this->board->sync($userId);
        } catch (\Throwable) {
            // 生成失败不影响看规则
        }

        $this->tpl->assign('rules', $this->board->rules($userId));

        $weekdayNames = [];
        foreach (range(1, 7) as $d) {
            $weekdayNames[$d] = Lang::t('week.'.$d);
        }
        $this->tpl->assign('weekdayNames', $weekdayNames);
        $this->tpl->assign('today', dtHelper()->userNow());

        return $this->tpl->display('calendar.showPlans');
    }
}
