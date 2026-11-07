<?php

namespace Shiri\Core\i18n;

use Illuminate\Support\ServiceProvider;

class LanguageServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(\Shiri\Core\Language::class, function () {
            return new \Shiri\Core\Language;
        });
        $this->app->alias(\Shiri\Core\Language::class, 'translator');
    }
}
