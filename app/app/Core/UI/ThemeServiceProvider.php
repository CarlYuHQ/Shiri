<?php

namespace Shiri\Core\UI;

use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(\Shiri\Core\UI\Theme::class, \Shiri\Core\UI\Theme::class);
        $this->app->alias(\Shiri\Core\UI\Theme::class, 'theme');
    }
}
