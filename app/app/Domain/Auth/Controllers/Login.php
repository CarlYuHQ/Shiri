<?php

namespace Shiri\Domain\Auth\Controllers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Shiri\Core\Configuration\Environment;
use Shiri\Core\Controller\Controller;
use Shiri\Core\Controller\Frontcontroller as FrontcontrollerCore;
use Shiri\Domain\Auth\Services\Auth as AuthService;
use Symfony\Component\HttpFoundation\Response;

class Login extends Controller
{
    private AuthService $authService;

    private Environment $config;

    /**
     * init - initialize private variables
     */
    public function init(
        AuthService $authService,
        Environment $config
    ): void {
        $this->authService = $authService;
        $this->config = $config;
    }

    /**
     * get - handle get requests
     *
     *
     *
     *
     * @throws BindingResolutionException
     */
    public function get(array $params): Response
    {
        // 本机单人使用（已开 127.0.0.1 自动登录），登录页没有存在意义：直接回日历。
        self::dispatchEvent('beforeAuth', $params);

        return FrontcontrollerCore::redirect(BASE_URL.'/calendar/showMyCalendar');
    }

    /**
     * post - handle post requests
     *
     *
     *
     *
     * @throws BindingResolutionException
     */
    public function post(array $params): Response
    {
        if (isset($_POST['username']) === true && isset($_POST['password']) === true) {

            // Same array guard as the GET path above — redirectUrl[]=x must not 500 the login POST.
            $rawRedirect = $_POST['redirectUrl'] ?? null;
            $redirectUrl = $this->authService->resolveSafeRedirect(is_string($rawRedirect) ? $rawRedirect : null);

            $username = trim($_POST['username']);
            $password = $_POST['password'];

            try {
                // Allow login interruptions through events
                self::dispatch_event('beforeAuthServiceCall', ['post' => $_POST]);

            } catch (\Exception $e) {

                $this->tpl->setNotification($e->getMessage(), 'error');

                return FrontcontrollerCore::redirect(BASE_URL.'/auth/login');
            }

            // If login successful redirect to the correct url to avoid post on reload
            if ($this->authService->login($username, $password) === true) {

                self::dispatch_event('successfulLogin', ['post' => $_POST]);

                if ($this->authService->use2FA()) {
                    return FrontcontrollerCore::redirect(BASE_URL.'/auth/twoFA');
                }

                return FrontcontrollerCore::redirect($redirectUrl);
            } else {
                $this->tpl->setNotification('notifications.username_or_password_incorrect', 'error');

                return FrontcontrollerCore::redirect(BASE_URL.'/auth/login');
            }
        } else {
            $this->tpl->setNotification('notifications.username_or_password_missing', 'error');

            return FrontcontrollerCore::redirect(BASE_URL.'/auth/login');
        }
    }
}
