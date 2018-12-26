<?php

namespace System\Database;

use System\Database\ORM\Model;
use System\Support\ServiceProvider;


class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the Application events.
     *
     * @return void
     */
    public function boot()
    {
        $resolver = $this->app['db'];

        $events = $this->app['events'];

        Model::setConnectionResolver($resolver);

        Model::setEventDispatcher($events);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('db', function ($app)
        {
            return new DatabaseManager($app);
        });
    }
}
