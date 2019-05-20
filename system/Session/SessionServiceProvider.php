<?php

namespace Mini\Session;

use Mini\Support\ServiceProvider;


class SessionServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerSessionDriver();

        $this->registerSessionManager();

        //
        $this->app->singleton('Mini\Session\Middleware\StartSession');
    }

    /**
     * Register the session manager instance.
     *
     * @return void
     */
    protected function registerSessionManager()
    {
        $this->app->singleton('session', function($app)
        {
            return new SessionManager($app);
        });
    }

    /**
     * Register the session driver instance.
     *
     * @return void
     */
    protected function registerSessionDriver()
    {
        $this->app->singleton('session.store', function($app)
        {
            $manager = $app['session'];

            return $manager->driver();
        });
    }
}
