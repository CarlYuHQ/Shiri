<?php

namespace Shiri\Domain\Projects\Hxcontrollers;

use Shiri\Core\Controller\Frontcontroller;
use Shiri\Core\Controller\HtmxController;
use Shiri\Domain\Menu\Services\Menu;
use Shiri\Domain\Projects\Services\Projects as ProjectService;

class ProjectHubProjects extends HtmxController
{
    protected static string $view = 'projects::partials.projectHubProjects';

    private ProjectService $projectsService;

    private Menu $menuService;

    /**
     * Controller constructor
     *
     * @param  \Shiri\Domain\Projects\Services\Projects  $projectsService  The projects domain service.
     * @param  \Shiri\Domain\Menu\Services\Menu  $menuService  The menu domain service.
     */
    public function init(
        ProjectService $projectsService,
        Menu $menuService
    ): void {
        $this->projectsService = $projectsService;
        $this->menuService = $menuService;
    }

    /**
     * Renders the project hub project list partial.
     */
    public function get(): void
    {
        $clientId = (isset($_GET['client']) === true && $_GET['client'] != '') ? (int) $_GET['client'] : null;

        $hubData = $this->projectsService->getProjectHubData(session('userdata.id'), $clientId);

        $currentUrlPath = BASE_URL.'/'.str_replace('.', '/', Frontcontroller::getCurrentRoute());

        $this->tpl->assign('projectTypeAvatars', $this->menuService->getProjectTypeAvatars());
        $this->tpl->assign('currentUrlPath', $currentUrlPath);
        $this->tpl->assign('currentClientName', $hubData['currentClientName']);
        $this->tpl->assign('currentClient', $hubData['currentClient']);
        $this->tpl->assign('clients', $hubData['clients']);
        $this->tpl->assign('allProjects', $hubData['allProjects']);
    }
}
