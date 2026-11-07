<?php

namespace Shiri\Domain\Plugins\Controllers;

use Shiri\Core\Controller\Controller;
use Shiri\Domain\Auth\Models\Roles;
use Shiri\Domain\Auth\Services\Auth;
use Shiri\Domain\Plugins\Services\Plugins as PluginService;
use Symfony\Component\HttpFoundation\Response;

class Details extends Controller
{
    private PluginService $pluginService;

    public function init(PluginService $pluginService): void
    {
        $this->pluginService = $pluginService;
    }

    public function get(): Response
    {

        Auth::authOrRedirect([Roles::$owner, Roles::$admin], true);

        if (! $this->incomingRequest->query->has('id')) {
            throw new \Exception('Plugin Identifier is required');
        }

        /**
         * @var \Shiri\Domain\Plugins\Models\MarketplacePlugin|false $plugin
         */
        $plugin = $this->pluginService->getMarketplacePlugin(
            $this->incomingRequest->query->get('id'),
        );

        if (! $plugin) {
            return $this->tpl->display('errors.error404', 'blank');
        }

        $this->tpl->assign('isBundle', $this->pluginService->isBundle($plugin));
        $this->tpl->assign('plugin', $plugin);

        return $this->tpl->display('plugins.plugindetails', 'blank');
    }
}
