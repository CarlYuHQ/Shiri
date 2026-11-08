<?php

/**
 * newClient Class - Add a new client
 */

namespace Shiri\Domain\Calendar\Controllers;

use Shiri\Core\Auth\Permissions\RequiresPermission;
use Shiri\Core\Controller\Controller;
use Shiri\Core\Controller\Frontcontroller;
use Shiri\Core\Support\FromFormat;
use Shiri\Domain\Calendar\Permissions\CalendarPermissions;
use Shiri\Domain\Calendar\Services\Calendar;
use Symfony\Component\HttpFoundation\Response;

class AddEvent extends Controller
{
    private Calendar $calendarService;

    /**
     * init - initialize private variables
     */
    public function init(Calendar $calendarService): void
    {
        $this->calendarService = $calendarService;
    }

    #[RequiresPermission(CalendarPermissions::CREATE)]
    public function get(array $params): Response
    {
        $values = [
            'description' => '',
            'dateFrom' => '',
            'dateTo' => '',
            'allDay' => '',
        ];

        $this->tpl->assign('values', $values);

        return $this->tpl->displayPartial('calendar.addEvent');
    }

    #[RequiresPermission(CalendarPermissions::CREATE)]
    public function post(array $params): Response
    {

        // Time comes in as 24:00 time from html5 element. Make it user date format
        $params['timeFrom'] = format(value: $params['timeFrom'], fromFormat: FromFormat::User24hTime)->userTime24toUserTime();
        $params['timeTo'] = format(value: $params['timeTo'], fromFormat: FromFormat::User24hTime)->userTime24toUserTime();
        $result = $this->calendarService->addEvent($params);

        if (is_numeric($result) === true) {
            $this->tpl->setNotification('notification.event_created_successfully', 'success');

            // 回带 closeModal：片段里的脚本关掉弹窗，日历由 modals.js 的 softReload 就地刷新。
            // 原来跳 /calendar/editEvent/{id}，nyroModal 会把编辑页当成成功响应注入弹窗，
            // 表现就是「点了保存弹窗不消失，还变成了编辑事件」。
            return Frontcontroller::redirect(BASE_URL.'/calendar/addEvent?closeModal=1');
        } else {
            $this->tpl->setNotification('notification.please_enter_title', 'error');
            $this->tpl->assign('values', $params);

            return $this->tpl->displayPartial('calendar.addEvent');
        }
    }
}
