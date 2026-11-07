<?php

namespace Shiri\Core;

use Illuminate\Log\Context\ContextServiceProvider;
use Illuminate\Log\LogServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;
use Shiri\Core\Configuration\Environment;
use Shiri\Core\Console\ConsoleKernel;
use Shiri\Core\Events\DispatchesEvents;
use Shiri\Core\Http\ApiRequest;
use Shiri\Core\Http\HttpKernel;
use Shiri\Core\Http\IncomingRequest;

/**
 * Class Application
 *
 * Represents an application.
 */
class Application extends \Illuminate\Foundation\Application
{
    use DispatchesEvents;

    /**
     * Constructor for the class.
     *
     * @param  string  $basePath  The base path for the application.
     * @return void
     */
    public function __construct($basePath = null)
    {

        $this->namespace = 'Shiri\\';

        if ($basePath) {
            $this->setBasePath($basePath);
        }

        // Our folder structure is different and we shall not bow to the bourgeoisie
        $this->useAppPath($this->basePath.'/app');
        $this->useConfigPath($this->basePath.'/config');
        $this->useEnvironmentPath($this->basePath.'/config');
        $this->useBootstrapPath($this->basePath.'/bootstrap');
        $this->usePublicPath($this->basePath.'/public');
        $this->useStoragePath($this->basePath.'/storage');
        $this->useLangPath($this->basePath.'/app/Language');
        $this->useDatabasePath($this->basePath.'/database');

        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();

        $this->registerCoreContainerAliases();
        // Overriding some of the aliases
        $this->registerShiriAliases();

        $this->registerLaravelCloudServices();

    }

    /**
     * Register the base service providers.
     *
     * This method is used to register the base service providers required for the application.
     *
     * @return void
     */
    protected function registerBaseServiceProviders()
    {
        // Loading some config vars so we can run events
        // $this->register(new \Shiri\Core\Providers\Environment($this));

        $this->register(new Events\EventsServiceProvider($this));
        $this->register(new LogServiceProvider($this));
        $this->register(new ContextServiceProvider($this));
        $this->register(new RoutingServiceProvider($this));

        // Todo: Add event and see if that works here.

    }

    public function registerShiriAliases()
    {
        foreach ([
            'app' => [self::class, \Illuminate\Contracts\Container\Container::class, Application::class, \Illuminate\Contracts\Foundation\Application::class, \Psr\Container\ContainerInterface::class],
            'config' => [Environment::class, \Illuminate\Config\Repository::class, \Illuminate\Contracts\Config\Repository::class],
            'request' => [IncomingRequest::class, ApiRequest::class, \Shiri\Core\Console\CliRequest::class, \Illuminate\Http\Request::class,  \Symfony\Component\HttpFoundation\Request::class],
        ] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }

        $this->alias(HttpKernel::class, \Illuminate\Foundation\Http\Kernel::class);
        $this->alias(ConsoleKernel::class, \Illuminate\Foundation\Console\Kernel::class);

    }

    // Boot with Shiri event dispatcher

    /**
     * Boot the application.
     *
     * @return void
     */
    public function boot()
    {

        // We need to discover events a lot earlier than Laravel wants us to.
        // So we're just doing it.
        \Illuminate\Support\Facades\Event::discoverListeners();

        // Calling the first event
        self::dispatchEvent('beforeBootingServiceProviders');

        parent::boot();

        self::dispatchEvent('afterBootingServiceProviders');

    }
}
