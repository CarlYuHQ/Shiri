<?php

/**
 * delClient Class - Deleting clients
 */

namespace Shiri\Domain\Calendar\Controllers;

use Shiri\Core\Auth\Permissions\RequiresPermission;
use Shiri\Core\Controller\Controller;
use Shiri\Core\Controller\Frontcontroller;
use Shiri\Domain\Calendar\Permissions\CalendarPermissions;
use Shiri\Domain\Calendar\Services\Calendar as CalendarService;
use Symfony\Component\HttpFoundation\Response;

class DelEvent extends Controller
{
    private CalendarService $calendarService;

    /**
     * init - initialize private variables
     */
    public function init(CalendarService $calendarService): void
    {
        $this->calendarService = $calendarService;
    }

    /**
     * retrieves delete calendar event page data
     */
    #[RequiresPermission(CalendarPermissions::DELETE)]
    public function get(array $params): Response
    {
        return $this->tpl->displayPartial('calendar.delEvent');
    }

    /**
     * sets, creates, and updates edit calendar event page data
     */
    #[RequiresPermission(CalendarPermissions::DELETE)]
    public function post(array $params): Response
    {

        if (isset($_GET['id']) === false) {
            return Frontcontroller::redirect(BASE_URL.'/calendar/showMyCalendar/');
        }

        $id = (int) $_GET['id'];
        $result = $this->calendarService->delEvent($id);

        if (is_numeric($result) === true) {
            $this->tpl->setNotification('notification.event_removed_successfully', 'success');

            return Frontcontroller::redirect(BASE_URL.'/calendar/showMyCalendar/');
        } else {
            $this->tpl->setNotification('notification.could_not_delete_event', 'error');

            return $this->tpl->displayPartial('calendar.delEvent');
        }
    }
}
