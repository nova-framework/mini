<?php

namespace System\Console\Scheduling;

use System\Support\ServiceProvider;


class ScheduleServiceProvider extends ServiceProvider
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
        $this->app->singleton('schedule', function ($app)
        {
            return new Schedule($app);
        });

        $this->app->singleton('command.schedule.run', function ($app)
        {
            return new ScheduleRunCommand($app['schedule']);
        });

        $this->commands('command.schedule.run');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            'schedule', 'command.schedule.run'
        );
    }
}
