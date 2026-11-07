<?php

namespace Shiri\Domain\Wiki\Controllers;

use Shiri\Core\Auth\Permissions\RequiresPermission;
use Shiri\Core\Controller\Controller;
use Shiri\Domain\Wiki\Permissions\WikiPermissions;
use Symfony\Component\HttpFoundation\Response;

class Templates extends Controller
{
    public function init(): void {}

    /**
     * @throws \Exception
     */
    #[RequiresPermission(WikiPermissions::VIEW)]
    public function get($params): Response
    {
        return $this->tpl->displayPartial('wiki.templates');
    }
}
