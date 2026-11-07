<?php

namespace Shiri\Domain\Connector\Controllers;

use Shiri\Core\Controller\Controller;
use Shiri\Domain\Auth\Models\Roles;
use Shiri\Domain\Auth\Services\Auth;
use Shiri\Domain\Connector\Services;

class Show extends Controller
{
    private Services\Providers $providerService;

    /**
     * constructor - initialize private variables
     */
    public function init(Services\Providers $projectService)
    {
        Auth::authOrRedirect([Roles::$owner, Roles::$admin], true);
        $this->providerService = $projectService;
    }

    /**
     * get - handle get requests
     */
    public function get($params)
    {
        $providers = $this->providerService->getProviders();

        $this->tpl->assign('providers', $providers);

        return $this->tpl->display('connector.show');
    }

    /**
     * post - handle post requests
     */
    public function post($params)
    {
        // Redirect.
    }
}
