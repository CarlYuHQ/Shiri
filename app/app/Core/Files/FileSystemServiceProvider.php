<?php

namespace Shiri\Core\Files;

use Illuminate\Filesystem\FilesystemServiceProvider as LaravelFilesystemServiceProvider;
use Shiri\Core\Files\Contracts\FileManagerInterface;

class FileSystemServiceProvider extends LaravelFilesystemServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

        parent::register();

        // Bind FileManagerInterface to FileManager implementation
        $this->app->singleton(
            Contracts\FileManagerInterface::class,
            \Shiri\Core\Files\FileManager::class
        );

    }
}
