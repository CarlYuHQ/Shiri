<?php

namespace Shiri\Domain\Auth\Controllers;

use Shiri\Core\Controller\Controller;
use Shiri\Core\Controller\Frontcontroller as FrontcontrollerCore;
use Shiri\Domain\Auth\Services\Auth as AuthService;
use Symfony\Component\HttpFoundation\Response;

class Logout extends Controller
{
    private AuthService $authService;

    /**
     * init - initialize private variables
     */
    public function init(AuthService $authService): void
    {
        $this->authService = $authService;
    }

    /**
     * get - handle get requests
     */
    public function get(array $params): Response
    {
        $this->authService->logout();

        return FrontcontrollerCore::redirect(BASE_URL.'/');
    }
}
