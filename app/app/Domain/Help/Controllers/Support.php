<?php

namespace Shiri\Domain\Help\Controllers;

use Shiri\Core\Controller\Controller;
use Shiri\Domain\Help\Services\Helper;

class Support extends Controller
{
    protected Helper $helpService;

    public function init(Helper $helpService)
    {
        $this->helpService = $helpService;

    }

    /**
     * get - handle get requests
     */
    public function get($params)
    {

        return $this->tpl->display('help.support');

    }
}
