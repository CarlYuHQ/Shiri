<?php

namespace Shiri\Domain\Help\Hxcontrollers;

use Shiri\Core\Controller\HtmxController;
use Shiri\Domain\Help\Services\Helper;
use Shiri\Domain\Users\Services\Users as UserService;

class HelperModal extends HtmxController
{
    protected static string $view = '';

    protected Helper $helperService;

    protected UserService $userService;

    /**
     * Controller constructor
     *
     * @param  Helper  $helperService  The help domain service.
     * @param  UserService  $userService  The users domain service.
     * @return void
     */
    public function init(
        Helper $helperService,
        UserService $userService
    ) {
        $this->helperService = $helperService;
        $this->userService = $userService;
    }

    public function get() {}

    public function dontShowAgain($params)
    {

        $modal = $params['modalId'] ?? '';
        $hidePermanently = ($params['hidePermanently'] ?? false) === 'on' ? true : false;

        if ($modal !== '') {
            $this->userService->updateUserSettings('modals', $modal, $hidePermanently);
        }

        return $this->tpl->emptyResponse();
    }
}
