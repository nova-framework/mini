<?php

namespace Mini\Encryption;

use Mini\Encryption\Encrypter;
use Mini\Support\ServiceProvider;


class EncryptionServiceProvider extends ServiceProvider
{
    /**
     * Register the Service Provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('encrypter', function($app)
        {
            return new Encrypter($app['config']['app.key']);
        });
    }
}

