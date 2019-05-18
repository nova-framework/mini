<?php

namespace System\Foundation;

use System\Console\Application as ConsoleApplication;
use System\Foundation\Application;


class Forge
{
    /**
     * The application instance.
     *
     * @var \System\Foundation\Application
     */
    protected $app;

    /**
     * The forge console instance.
     *
     * @var  \System\Console\Application
     */
    protected $forge;


    /**
     * Create a new forge command runner instance.
     *
     * @param  \System\Foundation\Application  $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get the forge console instance.
     *
     * @return \System\Console\Application
     */
    protected function getForge()
    {
        if (isset($this->forge)) {
            return $this->forge;
        }

        $this->app->loadDeferredProviders();

        $this->forge = ConsoleApplication::make($this->app);

        return $this->forge->boot();
    }

    /**
     * Dynamically pass all missing methods to console forge.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $instance = $this->getForge();

        return call_user_func_array(array($instance, $method), $parameters);
    }

}
