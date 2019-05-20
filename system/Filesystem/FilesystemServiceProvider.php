<?php

namespace Mini\Filesystem;

use Mini\Support\ServiceProvider;


class FilesystemServiceProvider extends ServiceProvider
{

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('files', function ($app)
        {
            return new Filesystem($app);
        });
    }

}
