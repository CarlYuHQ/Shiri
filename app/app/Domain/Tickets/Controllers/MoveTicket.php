<?php

namespace Shiri\Domain\Tickets\Controllers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Shiri\Core\Auth\Permissions\RequiresPermission;
use Shiri\Core\Controller\Controller;
use Shiri\Core\Controller\Frontcontroller as FrontcontrollerCore;
use Shiri\Domain\Projects\Services\Projects as ProjectService;
use Shiri\Domain\Tickets\Permissions\TicketsPermissions;
use Shiri\Domain\Tickets\Services\Tickets as TicketService;
use Symfony\Component\HttpFoundation\Response;

class MoveTicket extends Controller
{
    private TicketService $ticketService;

    private ProjectService $projectService;

    public function init(
        TicketService $ticketService,
        ProjectService $projectService
    ): void {
        $this->ticketService = $ticketService;
        $this->projectService = $projectService;
    }

    /**
     * @throws BindingResolutionException
     */
    #[RequiresPermission(TicketsPermissions::EDIT)]
    public function get($params): Response
    {
        $ticketId = $params['id'] ?? '';

        $ticket = $this->ticketService->getTicket($ticketId);

        if (! $ticket) {
            return $this->tpl->displayPartial('errors.error404', responseCode: 404);
        }

        $projects = $this->projectService->getProjectsAssignedToUser(session('userdata.id'));

        $this->tpl->assign('ticket', $ticket);
        $this->tpl->assign('projects', $projects);

        return $this->tpl->displayPartial('tickets.moveTicket');
    }

    /**
     * @throws BindingResolutionException
     */
    #[RequiresPermission(TicketsPermissions::EDIT)]
    public function post($params): Response
    {
        if (! empty($ticketId = (int) $_GET['id']) && ! empty($projectId = (int) $params['projectId'])) {
            if ($this->ticketService->moveTicket($ticketId, $projectId)) {
                $this->tpl->setNotification($this->language->__('text.ticket_moved'), 'success');
            } else {
                $this->tpl->setNotification($this->language->__('text.move_problem'), 'error');
            }
        }

        return FrontcontrollerCore::redirect(BASE_URL.'/tickets/moveTicket/'.$ticketId.'?closeModal=true');
    }
}
