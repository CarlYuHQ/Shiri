<?php

namespace Shiri\Core\Routing;

use Illuminate\Support\ServiceProvider;

class FrontcontrollerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('frontcontroller', \Shiri\Core\Controller\Frontcontroller::class);

    }
}
