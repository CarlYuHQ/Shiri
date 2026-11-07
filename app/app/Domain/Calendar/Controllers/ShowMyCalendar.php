<?php

namespace Shiri\Domain\Calendar\Controllers;

use Shiri\Core\Auth\Permissions\RequiresPermission;
use Shiri\Core\Controller\Controller;
use Shiri\Domain\Calendar\Permissions\CalendarPermissions;
use Shiri\Domain\Calendar\Support\Lang;
use Shiri\Domain\Calendar\Support\Prefs;
use Shiri\Domain\Calendar\Services\Calendar as CalendarService;
use Shiri\Domain\Calendar\Services\Milestones;
use Symfony\Component\HttpFoundation\Response;

class ShowMyCalendar extends Controller
{
    private CalendarService $calendarService;

    private Prefs $prefs;

    private Milestones $milestones;

    /**
     * Initializes dependencies.
     */
    public function init(CalendarService $calendarService, Prefs $prefs, Milestones $milestones): void
    {
        $this->calendarService = $calendarService;
        $this->prefs = $prefs;
        $this->milestones = $milestones;
    }

    /**
     * Displays the user's calendar view.
     *
     * @param  array  $params  Request parameters
     */
    #[RequiresPermission(CalendarPermissions::VIEW)]
    public function get(array $params): Response
    {
        // 先把语言偏好同步给 Shiri 本体，模板里的 __() 才会跟着切
        Lang::applyToShiri();
        $this->tpl->assign('calendar', $this->calendarService->getCalendar(session('userdata.id')));

        session(['lastPage' => BASE_URL.'/calendar/showMyCalendar/']);

        $externalCalendars = $this->calendarService->getMyExternalCalendars(session('userdata.id'));
        $externalCalendars = self::dispatch_filter('showMyCalendar.externalCalendars', $externalCalendars);
        $this->tpl->assign('externalCalendars', $externalCalendars);

        // 一周从周几开始：设置页的值要压过语言包里的默认值（FullCalendar 的 firstDay 用 0=周日 / 1=周一）
        $weekStart = $this->prefs->get((int) session('userdata.id'), Prefs::WEEK_START, 'sun');
        $this->tpl->assign('weekStartFirstDay', $weekStart === 'mon' ? 1 : 0);

        // 右侧栏：重要时间节点 + 遥远未来，倒计时天数在 Service 里按本机日期算好
        $msView = $this->milestones->view();
        $this->tpl->assign('msGroups', $msView['groups']);
        $this->tpl->assign('msCount', $msView['count']);

        return $this->tpl->display('calendar.showMyCalendar');
    }
}
