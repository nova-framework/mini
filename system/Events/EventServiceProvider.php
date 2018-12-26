<?php

namespace System\Events;

use System\Support\ServiceProvider;


class EventServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('events', function ($app)
        {
            return new Dispatcher($app);
        });
    }
}
