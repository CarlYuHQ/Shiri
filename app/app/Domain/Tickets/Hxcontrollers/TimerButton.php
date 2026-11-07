<?php

namespace Shiri\Domain\Tickets\Hxcontrollers;

use Shiri\Core\Controller\HtmxController;
use Shiri\Domain\Timesheets\Services\Timesheets;

class TimerButton extends HtmxController
{
    protected static string $view = 'tickets::partials.timerButton';

    private Timesheets $timesheetService;

    /**
     * Controller constructor
     */
    public function init(Timesheets $timesheetService): void
    {
        $this->timesheetService = $timesheetService;
    }

    public function getStatus(): void
    {

        $params = $this->incomingRequest->query->all();

        $onTheClock = session()->exists('userdata') ? $this->timesheetService->isClocked(session('userdata.id')) : false;
        $this->tpl->assign('onTheClock', $onTheClock);
        $this->tpl->assign('parentTicketId', $params['request_parts'] ?? false);
    }

    public function getStatusButton(): void
    {
        $this->getStatus();
        $this->tpl->displayPartial('tickets::partials.timerButton');
    }
}
