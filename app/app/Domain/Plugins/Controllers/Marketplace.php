<?php

namespace Shiri\Domain\Plugins\Controllers;

use Shiri\Core\Controller\Controller;
use Shiri\Domain\Auth\Models\Roles;
use Shiri\Domain\Auth\Services\Auth;
use Symfony\Component\HttpFoundation\Response;

class Marketplace extends Controller
{
    public function get(): Response
    {

        Auth::authOrRedirect([Roles::$owner, Roles::$admin], true);

        $this->tpl->assign('plugins', []);

        return $this->tpl->display('plugins.marketplace');
    }
}
