<?php

namespace Shiri\Domain\Projects\Middleware;

use Closure;
use Shiri\Core\Http\HtmxRequest;
use Shiri\Core\Http\IncomingRequest;
use Shiri\Domain\Auth\Services\Auth as AuthService;
use Shiri\Domain\Help\Services\Helper;
use Shiri\Domain\Projects\Services\Projects as ProjectService;
use Symfony\Component\HttpFoundation\Response;

class CurrentProject
{
    /**
     * Set the current project
     *
     * @param  \Closure(IncomingRequest): Response  $next
     **/
    public function handle(IncomingRequest $request, Closure $next): Response
    {

        if (app()->make(AuthService::class)->loggedIn()) {

            $actionPath = $request->getModuleName();

            // Only change/set project if the request is not htmx, api or cron
            if (! ($request instanceof HtmxRequest) && $actionPath != 'api' && $actionPath != 'cron') {
                app()->make(ProjectService::class)->setCurrentProject();

                // Ensure the user has a default project on first login.
                // This was previously triggered inside a view composer (Helpermodal)
                // which is unsafe since view composers should never perform writes.
                app()->make(Helper::class)->ensureDefaultProject(
                    session('userdata.id'),
                    session('userdata.role') ?? 'editor'
                );
            }
        }

        return $next($request);
    }
}
