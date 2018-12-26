<?php

namespace System\Validation;

use System\Support\ServiceProvider;


class ValidationServiceProvider extends ServiceProvider
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
        $this->registerPresenceVerifier();

        $this->app->singleton('validator', function ($app)
        {
            $validator = new Factory($app['translator'], $app);

            if ($app->bound('validation.presence')) {
                $validator->setPresenceVerifier($app['validation.presence']);
            }

            return $validator;
        });
    }

    /**
     * Register the database presence verifier.
     *
     * @return void
     */
    protected function registerPresenceVerifier()
    {
        $this->app->singleton('validation.presence', function ($app)
        {
            return new DatabasePresenceVerifier($app['db']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('validator', 'validation.presence');
    }
}
