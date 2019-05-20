<?php

namespace Mini\Cache;

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
        $this->app->singleton('command.cache.clear', function ($app)
        {
            return new Console\ClearCommand($app['cache'], $app['files']);
        });

        $this->commands('command.cache.clear');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('command.cache.clear');
    }

}
