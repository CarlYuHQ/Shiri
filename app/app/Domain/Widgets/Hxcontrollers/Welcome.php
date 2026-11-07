<?php

namespace Shiri\Domain\Widgets\Hxcontrollers;

use Shiri\Core\Controller\HtmxController;
use Shiri\Domain\Widgets\Services\Dashboard as DashboardService;

class Welcome extends HtmxController
{
    protected static string $view = 'widgets::partials.welcome';

    private DashboardService $dashboardService;

    /**
     * Initializes the class by assigning the dashboard service and setting the last page session variable.
     *
     * @param  DashboardService  $dashboardService  The dashboard aggregation service.
     */
    public function init(DashboardService $dashboardService): void
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Retrieves the Welcome widget data and assigns it to the template for display.
     *
     * @return void
     */
    public function get()
    {
        $this->dashboardService->sendAnonymousTelemetry();

        $welcomeData = $this->dashboardService->getWelcomeWidgetData((int) session('userdata.id'));

        array_map([$this->tpl, 'assign'], array_keys($welcomeData), array_values($welcomeData));
    }
}
