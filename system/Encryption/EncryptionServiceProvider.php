<?php

namespace System\Encryption;

use System\Encryption\Encrypter;
use System\Support\ServiceProvider;


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

