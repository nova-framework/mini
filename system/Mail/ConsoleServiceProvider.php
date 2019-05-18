<?php

namespace System\Mail;

use System\Support\ServiceProvider;


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
        $this->app->singleton('command.spool.flush', function ($app)
        {
            return new Console\SpoolFlushCommand();
        });

        $this->commands('command.spool.flush');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('command.spool.flush');
    }

}
