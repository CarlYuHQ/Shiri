<?php

namespace Shiri\Core\Http;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Shiri\Core\Controller\Frontcontroller;
use Shiri\Core\Events\DispatchesEvents;
use Shiri\Core\Middleware\AuthenticateSession;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HttpKernel extends Kernel
{
    use DispatchesEvents;

    protected $bootstrappers = [
        \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
        \Shiri\Core\Bootstrap\LoadConfig::class,
        \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
        \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
        \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
        \Illuminate\Foundation\Bootstrap\BootProviders::class,
    ];

    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [

        // \Illuminate\Session\Middleware\StartSession::class,
        // \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        // \Illuminate\Auth\Middleware\Authenticate::class,
        // \Illuminate\Session\Middleware\AuthenticateSession::class,
        // \Illuminate\Routing\Middleware\SubstituteBindings::class,
        // \Illuminate\Auth\Middleware\Authorize::class,
        // \Illuminate\Http\Middleware\TrustHosts::class,

        // \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        // \Illuminate\Cookie\Middleware\EncryptCookies::class,
        // \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,

        \Shiri\Core\Middleware\TrustProxies::class,

        \Shiri\Core\Middleware\StartSession::class,
        \Shiri\Core\Middleware\Installed::class,
        \Shiri\Core\Middleware\Updated::class,

        // All enabled plugins will be available from here on out
        \Shiri\Core\Middleware\LoadPlugins::class,

        \Shiri\Core\Middleware\InitialHeaders::class,
        // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,

        // CSRF verification is NOT yet global — ~82 legacy .tpl.php forms lack @csrf tokens.
        // Enable globally only after all forms are tokenized. Until then, apply per-route.
        // \Shiri\Core\Middleware\VerifyCsrfToken::class,
        \Shiri\Core\Middleware\VerifyRequestOrigin::class,

        \Shiri\Core\Middleware\AuthCheck::class,
        \Shiri\Core\Middleware\AuthenticateSession::class,

        \Shiri\Core\Middleware\RequestRateLimiter::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \Shiri\Core\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \Shiri\Core\Middleware\SetCacheHeaders::class,
        \Shiri\Core\Middleware\Localization::class,

        \Shiri\Domain\Projects\Middleware\CurrentProject::class,
        \Shiri\Core\Modules\Middleware\ProjectModuleGate::class,

    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
        ],
        'api' => [
        ],
        'hx' => [
        ],
    ];

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used instead of class names to conveniently assign middleware to routes and groups.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => \Shiri\Core\Middleware\AuthCheck::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        // 'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        // 'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
    ];

    public function handle($request)
    {
        $this->requestStartedAt = Carbon::now();

        try {
            $response = $this->sendRequestThroughRouter($request);
        } catch (\Throwable $e) {
            $this->reportException($e);

            $response = $this->renderException($request, $e);
        }

        // @phpstan-ignore-next-line argument.type
        $this->app['events']->dispatch(new RequestHandled($request, $response));

        $response = self::dispatch_filter('beforeSendResponse', $response);

        return $response;
    }

    /**
     * Send the request through the core and plugin middleware pipelines to the router.
     * The /health probe bypasses both pipelines (see isHealthCheck()).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function sendRequestThroughRouter($request)
    {
        $this->app->instance('request', $request);

        Facade::clearResolvedInstance('request');

        $this->bootstrap();

        // Events are discovered and available as part of bootstrapping the providers.
        // Can savely assume events are available here.
        self::dispatch_event('request_started', ['request' => $request]);

        // Container/uptime probes skip the middleware pipeline entirely: no session cookie per
        // probe, no install/update redirect, no auth, no rate limiting. See Status\Controllers\Health.
        if (self::isHealthCheck($request)) {
            return $this->router->dispatch($request);
        }

        //        if ($request instanceof ApiRequest) {
        //
        //            array_splice($this->middleware, 6, 0, $this->middlewareGroups['api']);
        //
        //        } else {
        //            array_splice($this->middleware, 6, 0, $this->middlewareGroups['web']);
        //        }

        // This filter only works for system plugins
        // Regular plugins are not available until after install verification
        $this->middleware = self::dispatch_filter('middleware', $this->middleware, ['request' => $request]);

        // Main Pipeline
        $response = (new \Illuminate\Routing\Pipeline($this->app))
            ->send($request)
            ->through($this->middleware)
            ->then(fn ($request) =>
                // Then run through plugin pipeline
            (new \Illuminate\Routing\Pipeline($this->app))
                ->send($request)
                ->through(self::dispatch_filter(
                    hook: 'plugins_middleware',
                    payload: [],
                    function: 'handle',
                ))
                ->then(fn () => $this->findAndDispatchToRouter($request))
            );

        return $response;
    }

    /**
     * Whether the request targets the public /health probe (GET/HEAD only).
     */
    public static function isHealthCheck(\Symfony\Component\HttpFoundation\Request $request): bool
    {
        return $request->getPathInfo() === '/health'
            && in_array($request->getMethod(), ['GET', 'HEAD'], true);
    }

    public function terminate($request, $response)
    {

        self::dispatchEvent('request_terminated', [$request, $response]);

        parent::terminate($request, $response);

    }

    /**
     * Dispatch request to router with Laravel routing precedence
     *
     * Tries Laravel routing first, falls back to Frontcontroller if no route found
     *
     * Frontcontroller is now deprecated and will be removed in future versions once we have route files for everythihng
     */
    protected function findAndDispatchToRouter($request)
    {

        $this->app->instance('request', $request);

        try {
            // Try Laravel routing first
            $this->router->getRoutes()->match($request);

            // Use Laravel's router to handle the request
            return $this->router->dispatch($request);
        } catch (NotFoundHttpException $e) {
            // No Laravel route found, fall back to Frontcontroller
        } catch (\Exception $e) {
            // Log other exceptions but continue to Frontcontroller
            if (config('app.debug')) {
                Log::error($e);
            }
        }

        // Fall back to Shiri's Frontcontroller routing
        return Frontcontroller::dispatch_request($request);
    }
}
