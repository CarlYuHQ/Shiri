<?php

namespace Shiri\Domain\Timesheets\Hxcontrollers;

use Error;
use Shiri\Core\Auth\Permissions\RequiresPermission;
use Shiri\Core\Controller\HtmxController;
use Shiri\Domain\Timesheets\Permissions\TimesheetsPermissions;
use Shiri\Domain\Timesheets\Services\Timesheets;

class Stopwatch extends HtmxController
{
    protected static string $view = 'timesheets::partials.stopwatch';

    private Timesheets $timesheetService;

    /**
     * Controller constructor
     */
    public function init(Timesheets $timesheetService): void
    {
        $this->timesheetService = $timesheetService;
    }

    /**
     * show stop watch
     */
    #[RequiresPermission(TimesheetsPermissions::VIEW, global: true)]
    public function getStatus(): void
    {

        $onTheClock = session()->exists('userdata') ? $this->timesheetService->isClocked(session('userdata.id')) : false;
        $this->tpl->assign('onTheClock', $onTheClock);
    }

    /**
     * show stop watch
     */
    #[RequiresPermission(TimesheetsPermissions::CREATE, global: true)]
    public function stopTimer(): void
    {
        if ($this->incomingRequest->getMethod() !== 'PATCH') {
            throw new Error('This endpoint only supports PATCH requests');
        }

        $params = $this->incomingRequest->request->all();

        if (isset($params['action']) && $params['action'] === 'stop') {
            $ticketId = (int) filter_var($params['ticketId'], FILTER_SANITIZE_NUMBER_INT);
            $hoursBooked = $this->timesheetService->punchOut($ticketId);
        }

        $this->tpl->setHTMXEvent('timerUpdate');

        $onTheClock = session()->exists('userdata') ? $this->timesheetService->isClocked(session('userdata.id')) : false;
        $this->tpl->assign('onTheClock', $onTheClock);
    }

    #[RequiresPermission(TimesheetsPermissions::CREATE, global: true)]
    public function startTimer(): void
    {
        if ($this->incomingRequest->getMethod() !== 'PATCH') {
            throw new Error('This endpoint only supports PATCH requests');
        }

        $params = $this->incomingRequest->request->all();

        if (isset($params['action']) && $params['action'] === 'start') {
            $ticketId = (int) filter_var($params['ticketId'], FILTER_SANITIZE_NUMBER_INT);

            if ($ticketId > 0) {
                $result = $this->timesheetService->punchIn($ticketId);

                if ($result) {
                    $this->tpl->setNotification(__('short_notifications.timer_started'), 'success');
                } else {
                    $this->tpl->setNotification(__('short_notifications.timer_start_failed'), 'error');
                }
            }
        }

        $this->tpl->setHTMXEvent('timerUpdate');

        $onTheClock = session()->exists('userdata') ? $this->timesheetService->isClocked(session('userdata.id')) : false;
        $this->tpl->assign('onTheClock', $onTheClock);
    }
}
