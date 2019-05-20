<?php

namespace Mini\Exceptions;

use Mini\Exceptions\Handler;
use Mini\Support\ServiceProvider;


class ExceptionServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('exception', function ($app)
        {
            return new Handler($app);
        });
    }
}
