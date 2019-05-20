<?php

namespace Mini\Log;

use Mini\Support\ServiceProvider;


class LogServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('log', function ($app)
        {
            return new Writer($app);
        });
    }
}
