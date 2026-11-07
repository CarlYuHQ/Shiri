<?php

namespace Shiri\Domain\Projects\Hxcontrollers;

use Shiri\Core\Controller\Frontcontroller;
use Shiri\Core\Controller\HtmxController;
use Shiri\Domain\Menu\Services\Menu;
use Shiri\Domain\Projects\Services\Projects as ProjectService;

class ProjectCardProgress extends HtmxController
{
    protected static string $view = 'projects::partials.projectCardProgressBar';

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
     * Renders the project card progress bar partial.
     */
    public function getProgress(): void
    {
        $projectId = $_GET['pId'];

        $project = $this->projectsService->getProjectCardData($projectId);

        $projectTypeAvatars = $this->menuService->getProjectTypeAvatars();

        $currentUrlPath = BASE_URL.'/'.str_replace('.', '/', Frontcontroller::getCurrentRoute());

        $this->tpl->assign('projectTypeAvatars', $projectTypeAvatars);
        $this->tpl->assign('currentUrlPath', $currentUrlPath);
        $this->tpl->assign('project', $project);
        $this->tpl->assign('type', 'full');
    }
}
