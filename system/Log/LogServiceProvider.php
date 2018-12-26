<?php

namespace System\Log;

use System\Support\ServiceProvider;


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
