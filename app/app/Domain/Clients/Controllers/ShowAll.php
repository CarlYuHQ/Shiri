<?php

namespace Shiri\Domain\Clients\Controllers;

use Shiri\Core\Auth\Permissions\RequiresPermission;
use Shiri\Core\Controller\Controller;
use Shiri\Domain\Clients\Permissions\ClientsPermissions;
use Shiri\Domain\Clients\Services\Clients as ClientService;
use Symfony\Component\HttpFoundation\Response;

/**
 * ShowAll Controller - Show all clients.
 */
class ShowAll extends Controller
{
    private ClientService $clientService;

    /**
     * Initializes dependencies.
     */
    public function init(ClientService $clientService): void
    {
        $this->clientService = $clientService;
    }

    /**
     * Displays the list of all clients.
     *
     * @param  array  $params  Request parameters
     */
    #[RequiresPermission(ClientsPermissions::VIEW, global: true)]
    public function get(array $params): Response
    {
        if (session('userdata.role') == 'admin') {
            $this->tpl->assign('admin', true);
        }

        $this->tpl->assign('allClients', $this->clientService->getAll());

        return $this->tpl->display('clients.showAll');
    }
}
