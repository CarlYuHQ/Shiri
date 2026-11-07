<?php

namespace Shiri\Domain\Projects\Hxcontrollers;

use Shiri\Core\Controller\Frontcontroller;
use Shiri\Core\Controller\HtmxController;
use Shiri\Domain\Menu\Services\Menu;
use Shiri\Domain\Projects\Services\Projects as ProjectService;
use Shiri\Domain\Reactions\Services\Reactions;

class ProjectCard extends HtmxController
{
    protected static string $view = 'projects::partials.projectCard';

    private ProjectService $projectsService;

    private Menu $menuService;

    private Reactions $reactionService;

    /**
     * Controller constructor
     *
     * @param  \Shiri\Domain\Projects\Services\Projects  $projectsService  The projects domain service.
     * @param  \Shiri\Domain\Menu\Services\Menu  $menuService  The menu domain service.
     * @param  \Shiri\Domain\Reactions\Services\Reactions  $reactionService  The reactions domain service.
     */
    public function init(
        ProjectService $projectsService,
        Menu $menuService,
        Reactions $reactionService
    ): void {
        $this->projectsService = $projectsService;
        $this->menuService = $menuService;
        $this->reactionService = $reactionService;
    }

    public function get() {}

    /**
     * Toggles the current user's favorite reaction for a project and re-renders the card.
     */
    public function toggleFavorite(): void
    {
        $projectData = $this->incomingRequest->request->all();

        $projectId = (int) ($projectData['projectId'] ?? 0);
        $isFavorite = $projectData['isFavorite'] ?? false;

        // react()/unreact() act as the session user and require access to the project.
        if ($isFavorite) {
            $this->reactionService->unreact('project', $projectId, 'favorite');
        } else {
            $this->reactionService->react('project', $projectId, 'favorite');
        }

        $this->tpl->setHTMXEvent('HTMX.updateProjectList');

        $this->tpl->assign('project', $this->projectsService->getProject($projectId));
    }

    /**
     * Renders the project card for a project.
     */
    public function getProgress(): void
    {
        $projectId = $_GET['projectId'];

        $projectTypeAvatars = $this->menuService->getProjectTypeAvatars();

        $currentUrlPath = BASE_URL.'/'.str_replace('.', '/', Frontcontroller::getCurrentRoute());

        $this->tpl->assign('projectTypeAvatars', $projectTypeAvatars);
        $this->tpl->assign('currentUrlPath', $currentUrlPath);
        $this->tpl->assign('project', $this->projectsService->getProject($projectId));
        $this->tpl->assign('type', 'full');
    }
}
