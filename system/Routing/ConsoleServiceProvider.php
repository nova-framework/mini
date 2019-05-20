<?php

namespace Mini\Routing;

use Mini\Support\ServiceProvider;


class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;


    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('command.route.list', function ($app)
        {
            return new Console\RouteListCommand($app['router']);
        });

        $this->commands('command.route.list');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('command.route.list');
    }

}
