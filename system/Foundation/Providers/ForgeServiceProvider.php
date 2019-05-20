<?php

namespace Mini\Foundation\Providers;

use Mini\Foundation\Console\ClearLogCommand;
use Mini\Foundation\Console\KeyGenerateCommand;
use Mini\Foundation\Console\OptimizeCommand;
use Mini\Foundation\Console\ServeCommand;
use Mini\Foundation\Forge;
use Mini\Support\Composer;
use Mini\Support\ServiceProvider;


class ForgeServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the Provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = array(
        'ClearLog'         => 'command.clear-log',
        'KeyGenerate'      => 'command.key.generate',
        'Optimize'         => 'command.optimize',
        'Serve'            => 'command.serve',
    );

    /**
     * Register the Service Provider.
     *
     * @return void
     */
    public function register()
    {
        foreach (array_keys($this->commands) as $command) {
            $method = "register{$command}Command";

            call_user_func_array(array($this, $method), array());
        }

        $this->commands(array_values($this->commands));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerClearLogCommand()
    {
        $this->app->singleton('command.clear-log', function ($app)
        {
            return new ClearLogCommand($app['files']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerKeyGenerateCommand()
    {
        $this->app->singleton('command.key.generate', function ($app)
        {
            return new KeyGenerateCommand($app['files']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerOptimizeCommand()
    {
        $this->app->singleton('command.optimize', function ($app)
        {
            return new OptimizeCommand($app['composer']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerServeCommand()
    {
        $this->app->singleton('command.serve', function ()
        {
            return new ServeCommand();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array_values($this->commands);
    }

}
