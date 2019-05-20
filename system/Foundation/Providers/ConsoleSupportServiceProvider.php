<?php

namespace Mini\Foundation\Providers;

use Mini\Foundation\Forge;
use Mini\Support\Composer;
use Mini\Support\ServiceProvider;


class ConsoleSupportServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the Provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;


    /**
     * Register the Service Provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('composer', function($app)
        {
            return new Composer($app['files'], $app['path.base']);
        });

        $this->app->singleton('forge', function($app)
        {
           return new Forge($app);
        });

        // Register the additional service providers.
        $this->app->register('Mini\Foundation\Providers\ForgeServiceProvider');
        $this->app->register('Mini\Console\Scheduling\ScheduleServiceProvider');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('composer', 'forge');
    }

}
