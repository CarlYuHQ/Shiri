<?php

namespace Shiri\Core\Configuration;

use Illuminate\Support\ServiceProvider;
use Shiri\Core\Events\DispatchesEvents;

class EnvironmentServiceProvider extends ServiceProvider
{
    use DispatchesEvents;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            \Shiri\Core\Configuration\AppSettings::class, \Shiri\Core\Configuration\AppSettings::class);
        $this->app->singleton(
            \Shiri\Core\Configuration\Environment::class, \Shiri\Core\Configuration\Environment::class);

    }

    public function boot()
    {
        self::dispatchEvent('config_initialized');
    }
}
