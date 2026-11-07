<?php

namespace Shiri\Core\Plugins;

use Illuminate\Support\ServiceProvider;

class PluginsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(\Shiri\Domain\Plugins\Services\Plugins::class, \Shiri\Domain\Plugins\Services\Plugins::class);
    }
}
