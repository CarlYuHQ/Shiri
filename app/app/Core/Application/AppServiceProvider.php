<?php

namespace Shiri\Core\Application;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Shiri\Core\Configuration\AppSettings;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        AboutCommand::add('Environment', [
            'Shiri App Version' => fn () => $this->app->make(AppSettings::class)->appVersion,
            'Shiri Db Version' => fn () => $this->app->make(AppSettings::class)->dbVersion,
        ]);

    }
}
