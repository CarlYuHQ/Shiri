<?php

namespace Shiri\Domain\Clients\Controllers;

use Shiri\Core\Auth\Permissions\RequiresPermission;
use Shiri\Core\Controller\Controller;
use Shiri\Core\Controller\Frontcontroller;
use Shiri\Core\Exceptions\EntityExistsException;
use Shiri\Core\Exceptions\MissingParameterException;
use Shiri\Domain\Clients\Permissions\ClientsPermissions;
use Shiri\Domain\Clients\Services\Clients as ClientService;
use Symfony\Component\HttpFoundation\Response;

/**
 * NewClient Controller - Add a new client.
 */
class NewClient extends Controller
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
     * Displays the new client form.
     *
     * @param  array  $params  Request parameters
     */
    #[RequiresPermission(ClientsPermissions::CREATE, global: true)]
    public function get(array $params): Response
    {
        $values = [
            'name' => '',
            'street' => '',
            'zip' => '',
            'city' => '',
            'state' => '',
            'country' => '',
            'phone' => '',
            'internet' => '',
            'email' => '',
        ];

        $this->tpl->assign('values', $values);

        return $this->tpl->display('clients.newClient');
    }

    /**
     * Handles new client form submission.
     *
     * @param  array  $params  Request parameters
     */
    #[RequiresPermission(ClientsPermissions::CREATE, global: true)]
    public function post(array $params): Response
    {
        $values = [
            'name' => $_POST['name'] ?? '',
            'street' => $_POST['street'] ?? '',
            'zip' => $_POST['zip'] ?? '',
            'city' => $_POST['city'] ?? '',
            'state' => $_POST['state'] ?? '',
            'country' => $_POST['country'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'internet' => $_POST['internet'] ?? '',
            'email' => $_POST['email'] ?? '',
        ];

        try {
            $id = $this->clientService->createClient($values);
            $this->tpl->setNotification($this->language->__('notification.client_added_successfully'), 'success', 'new_client');

            return Frontcontroller::redirect(BASE_URL.'/clients/showClient/'.$id);
        } catch (EntityExistsException) {
            $this->tpl->setNotification($this->language->__('notification.client_exists_already'), 'error');
        } catch (MissingParameterException) {
            $this->tpl->setNotification($this->language->__('notification.client_name_not_specified'), 'error');
        }

        $this->tpl->assign('values', $values);

        return $this->tpl->display('clients.newClient');
    }
}
