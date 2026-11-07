<?php

namespace Shiri\Core\Middleware;

use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Shiri\Core\Configuration\Environment;
use Shiri\Core\Events\DispatchesEvents;
use Shiri\Core\Http\ApiRequest;
use Shiri\Core\Http\IncomingRequest;
use Shiri\Domain\Auth\Guards\WebGuard;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class AuthCheck
{
    use DispatchesEvents;

    /**
     * Public actions
     */
    private array $publicActions = [
        'auth.login',
        'auth.resetPw',
        'auth.userInvite',
        'install',
        'install.index',
        'install.update',
        'errors.error404',
        'errors.error500',
        'api.i18n',
        'api.static-asset',
        'calendar.ical',
        'oidc.login',
        'oidc.callback',
        'oidc.mobile',
        'status',
        'status.index',
        'cron.run',
        'auth.callback',
        'auth.redirect',
    ];

    public function __construct(
        protected Environment $config,
        protected Auth $auth,
        protected AuthManager $authManager,
    ) {
        $this->publicActions = self::dispatchFilter('publicActions', $this->publicActions, ['bootloader' => $this]);
    }

    /**
     * Handle the request
     */
    public function handle(IncomingRequest $request, Closure $next): Response
    {
        // API, cron and MCP endpoints must be addressed by their canonical path. A path that only
        // resolves to them after decoding/normalization (e.g. /%61pi/jsonrpc, /api//jsonrpc) is
        // rejected outright so classification and routing can never disagree. Static assets are
        // exempt: they are plain files whose names may legitimately be percent-encoded.
        if ($this->mustUseCanonicalPath($request) && $request->hasNonCanonicalPath()) {
            return new Response(json_encode(['error' => 'Invalid request path']), Response::HTTP_BAD_REQUEST);
        }

        if ($this->isPublicController($request->getCurrentRoute())) {
            return $next($request);
        }

        // Throttle credential brute force on token-authenticated endpoints (/api, /mcp). This
        // must live here rather than in RequestRateLimiter: that middleware runs AFTER AuthCheck,
        // so a failed-auth 401 short-circuits the pipeline before any request limit is counted.
        if ($request instanceof ApiRequest && $this->tooManyFailedAuthAttempts($request)) {
            return new Response(
                json_encode(['error' => 'Too many failed authentication attempts. Try again later.']),
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        $loginRedirect = self::dispatch_filter('loginRoute', 'auth.login', ['request' => $request]);

        if ($request instanceof ApiRequest) {
            self::dispatchEvent('before_api_request', ['application' => app()], 'shiri.core.middleware.apiAuth.handle');
        }

        $authCheckResponse = $this->authenticate($request, array_keys($this->config->get('auth.guards')), $loginRedirect, $next);

        // If auth fails either return json response or do the redirect
        if ($authCheckResponse !== true) {
            return $authCheckResponse;
        }

        self::dispatchEvent('logged_in', ['application' => $this]);

        return $next($request);
    }

    protected function authenticate($request, array $guards, $loginRedirect, $next)
    {
        if ($request->isApiOrCronRequest() || $request->isMcpRequest()) {
            return $this->authenticateApi($request, $guards);
        }

        return $this->authenticateWeb($request, $guards, $loginRedirect, $next);
    }

    protected function authenticateWeb(IncomingRequest $request, array $guards, string $loginRedirect, Closure $next): bool|Response
    {
        // Local auto-login (desktop wrapper). When LEAN_AUTOLOGIN_USER is set and the request
        // reaches PHP over loopback — i.e. through the local nginx on 127.0.0.1 — establish the
        // session directly instead of bouncing the user to the login form. Guarded by the env
        // var, so removing it restores the normal password flow; never honoured for a remote
        // client, so it cannot be used to bypass the login form from the network.
        $this->maybeAutoLogin($request);

        $authenticated = false;
        $response = null;

        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                $this->auth->shouldUse($guard);

                // Check two-factor authentication
                if (! $this->sessionTwoFASatisfied()) {
                    $response = $this->redirectWithOrigin('twoFA.verify', $_GET['redirect'] ?? '', $request) ?: $next($request);
                } else {
                    $authenticated = true;
                }

                break;
            }
        }

        if (! $authenticated && ! $response) {
            if ($request instanceof ApiRequest) {
                $this->hitFailedAuthLimiter($request);
                $response = new Response(json_encode(['error' => 'Invalid API Key']), 401);
            } else {
                $response = $this->redirectWithOrigin($loginRedirect, $request->getRequestUri(), $request) ?: $next($request);
            }
        }

        return $authenticated ? true : $response;
    }

    /**
     * Authenticate a token-authenticated request (JSON-RPC, cron, MCP).
     *
     * The stateful web session guard is only honoured for same-origin XHR calls (the app's own
     * JavaScript) whose session has completed two-factor verification. Anything else — a top-level
     * navigation or cross-site form riding the session cookie, or a session still waiting on 2FA —
     * must authenticate with an API key or Bearer token.
     *
     * @return true|Response True when authenticated, otherwise the 401 response.
     */
    protected function authenticateApi(IncomingRequest $request, array $guards): bool|Response
    {
        $sessionAwaitingTwoFA = false;

        foreach ($guards as $guard) {
            try {
                $guardInstance = $this->auth->guard($guard);

                if ($guardInstance instanceof WebGuard) {
                    if (! $request->ajax()) {
                        continue;
                    }

                    if (! $this->sessionTwoFASatisfied()) {
                        $sessionAwaitingTwoFA = $sessionAwaitingTwoFA || $guardInstance->check();

                        continue;
                    }
                }

                if ($guardInstance->check()) {
                    $this->auth->shouldUse($guard);
                    $request->attributes->set(AuthenticateSession::TOKEN_AUTHENTICATED, $guard !== 'shiri');

                    $this->establishApiUserSession($request);

                    return true;
                }
            } catch (\Throwable $e) {
                // A guard that throws while evaluating this request type must not abort the chain;
                // keep trying the others, then fall through to the Bearer / 401 handling below.
                // Logged at debug, not warning: a misconfigured/disabled guard would throw on
                // every API request, so warning-level here would flood production logs for a
                // condition we recover from cleanly. Debug keeps it available when investigating.
                Log::debug('API auth guard "'.$guard.'" threw while evaluating the request', ['exception' => $e]);

                continue;
            }
        }

        // Bearer / personal-access-token fallback. Shiri mints plain Str::random tokens
        // (sha256-hashed in zp_access_tokens) — NOT Sanctum's {id}|{plaintext} format — so
        // Sanctum's guard never resolves them and Bearer auth 401s. Validate against the core
        // token store directly (the same path the McpServer + AuthUser provider use), so Bearer
        // auth works for the mobile app and AdvancedAuth integrators independent of Sanctum's
        // token format or the plugin. getUserByToken enforces expiry and returns the user row.
        // Use ApiRequest::getBearerToken(), not Laravel's $request->bearerToken(): the latter only
        // reads the plain `Authorization` header, which Apache does not expose to PHP's header bag
        // here (it lands in HTTP_AUTHORIZATION / REDIRECT_HTTP_AUTHORIZATION). getBearerToken()
        // checks those variants, so this fires where bearerToken() silently returned null.
        $bearer = method_exists($request, 'getBearerToken') ? $request->getBearerToken() : $request->bearerToken();

        if (! empty($bearer)) {
            $user = app(\Shiri\Domain\Auth\Services\Auth::class)->getUserByToken($bearer);

            if (is_array($user) && ! empty($user['id'])) {
                // Establish the Shiri user context the permission engine (and the rest of the
                // app) reads — session('userdata'). Deliberately NOT setting a request user
                // resolver: leaving $request->user() null lets AuthenticateSession bail instead of
                // calling viaRemember() on the non-session WebGuard, matching the x-api-key path.
                app(\Shiri\Domain\Api\Services\Api::class)->setApiUserSession($user, true);
                $request->attributes->set(AuthenticateSession::TOKEN_AUTHENTICATED, true);

                return true;
            }
        }

        // A logged-in browser session that has not finished 2FA, and that presented no API key or
        // Bearer token, is not a credential-guessing attempt; reject it without counting against
        // the per-IP failed-auth budget. Any attempted token is always counted.
        if ($sessionAwaitingTwoFA && ! $this->presentedApiCredential($request)) {
            return new Response(json_encode(['error' => 'Two-factor authentication required']), 401);
        }

        $this->hitFailedAuthLimiter($request);

        return new Response(json_encode(['error' => 'Unauthorized']), 401);
    }

    /**
     * Establish a session for LEAN_AUTOLOGIN_USER on loopback requests only.
     *
     * Used by the local desktop wrapper so opening the app never asks for a password. Returns
     * silently when the env var is unset, the session already carries a user, the client is not
     * on loopback, or the configured account does not exist / is not active.
     */
    protected function maybeAutoLogin(IncomingRequest $request): void
    {
        // env() reads the runtime .env; getenv() is the fallback so this keeps working even if
        // the config gets cached (env() returns null once config:cache has run).
        $username = trim((string) (env('LEAN_AUTOLOGIN_USER') ?: (getenv('LEAN_AUTOLOGIN_USER') ?: '')));

        if ($username === '' || session()->exists('userdata')) {
            return;
        }

        if (! in_array($request->getClientIp(), ['127.0.0.1', '::1'], true)) {
            return;
        }

        try {
            $user = app(\Shiri\Domain\Users\Repositories\Users::class)->getUserByEmail($username);

            if (! is_array($user) || empty($user['id'])) {
                Log::warning('Auto-login skipped: LEAN_AUTOLOGIN_USER "'.$username.'" not found or not active.');

                return;
            }

            app(\Shiri\Domain\Auth\Services\Auth::class)->setUserSession($user);

            // setUserSession() always stamps twoFAVerified=false (the interactive flow sets it
            // later). A loopback auto-login has no second factor to present, so mark the session
            // as satisfied here — otherwise a user with 2FA enabled would still hit the code page.
            session(['userdata.twoFAVerified' => true]);

            Log::debug('Auto-login: session established for '.$username.' from '.$request->getClientIp());
        } catch (\Throwable $e) {
            Log::warning('Auto-login failed: '.$e->getMessage());
        }
    }

    /**
     * Whether $request targets an endpoint that must be addressed by its canonical path: any API
     * path (except static assets), cron, or MCP.
     */
    protected function mustUseCanonicalPath(IncomingRequest $request): bool
    {
        if ($request->isApiOrCronRequest() || $request->isMcpRequest()) {
            return true;
        }

        if (! $request->isApiRequest()) {
            return false;
        }

        return ($request->normalizedSegments()[1] ?? '') !== 'static-asset';
    }

    /**
     * Whether the request carries an explicit API credential (API key or Bearer token).
     */
    protected function presentedApiCredential(IncomingRequest $request): bool
    {
        if ($request->headers->has('x-api-key')) {
            return true;
        }

        $bearer = method_exists($request, 'getBearerToken') ? $request->getBearerToken() : $request->bearerToken();

        return ! empty($bearer);
    }

    /**
     * Whether the current session satisfies two-factor authentication: either 2FA is not enabled
     * for the user, or the code has been verified in this session. Mirrors the web check in
     * {@see self::authenticateWeb()}.
     */
    protected function sessionTwoFASatisfied(): bool
    {
        return ! session('userdata.twoFAEnabled') || (bool) session('userdata.twoFAVerified');
    }

    /**
     * Whether this client IP has exceeded the failed-authentication budget (shared with the
     * login attempt limit, LEAN_RATELIMIT_AUTH). Counted per minute.
     */
    protected function tooManyFailedAuthAttempts(IncomingRequest $request): bool
    {
        $limit = $this->config->ratelimitAuth ?? 20;

        return app(RateLimiter::class)->tooManyAttempts($this->failedAuthKey($request), $limit);
    }

    /**
     * Record a failed authentication attempt for this client IP (1-minute decay).
     */
    protected function hitFailedAuthLimiter(IncomingRequest $request): void
    {
        app(RateLimiter::class)->hit($this->failedAuthKey($request), 60);
    }

    /**
     * Rate limiter key for failed token-auth attempts, scoped to the client IP.
     */
    protected function failedAuthKey(IncomingRequest $request): string
    {
        return 'api-auth-failures:'.$request->getClientIp();
    }

    /**
     * Establish the Shiri user context (`session('userdata')`) for an authenticated API request.
     *
     * Every API guard must leave the SAME context behind: the permission engine — and everything
     * else — reads the user's id and role from `session('userdata')`. The x-api-key guard populates
     * it as a side effect of {@see \Shiri\Domain\Api\Services\Api::getAPIKeyUser()}, but the
     * Sanctum (Bearer) guard resolves the user straight from its token and never does — so the
     * engine saw no user and denied every gated `@api` method with -32001 on Bearer requests.
     *
     * This makes the HTTP API auth path uniform: whichever guard authenticated, the context is
     * built once, from the canonical user row, through the same `setApiUserSession()` builder the
     * x-api-key path uses. Idempotent — it skips when a guard already populated `userdata`
     * (x-api-key, or a stateful web session), so those paths are byte-for-byte untouched. Services
     * are resolved lazily because this only runs for an authenticated API request.
     */
    protected function establishApiUserSession(IncomingRequest $request): void
    {
        if (session()->exists('userdata') || ($apiUser = $request->user()) === null) {
            return;
        }

        $userData = app(\Shiri\Domain\Users\Services\Users::class)->getUser((int) $apiUser->id);

        if (is_array($userData)) {
            app(\Shiri\Domain\Api\Services\Api::class)->setApiUserSession($userData, true);
        }
    }

    /**
     * Redirect with origin
     * Returns false if the current route is already the redirection route.
     *
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function redirectWithOrigin(string $route, string $origin, IncomingRequest $request): false|RedirectResponse
    {

        $uri = ltrim(str_replace('.', '/', $route), '/');
        $destination = BASE_URL.'/'.$uri;
        $originClean = Str::replaceStart('/', '', $origin);
        $queryParams = ! empty($origin) && $origin !== '/' ? '?'.http_build_query(['redirect' => $originClean]) : '';

        if ($request->getCurrentRoute() === $route) {
            return false;
        }

        return new RedirectResponse($destination.$queryParams);
    }

    public function isPublicController($currentPath): bool
    {

        // path comes in with dots as separator.
        // We only need to compare the first 2 segments
        if (empty($currentPath)) {
            return false;
        }

        $pathSegments = explode('.', $currentPath);

        $routeToCheck = match (count($pathSegments)) {
            1 => $pathSegments[0],
            default => $pathSegments[0].'.'.$pathSegments[1],
        };

        return in_array($routeToCheck, $this->publicActions, true);

    }
}
