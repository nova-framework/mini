<?php

namespace App\Providers;

use Mini\Routing\Router;
use Mini\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;


class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to the controller routes in your routes file.
     *
     * @var string
     */
    protected $namespace = 'App\Controllers';


    /**
     * Define your route pattern filters, etc.
     *
     * @param  \Mini\Routing\Router  $router
     * @return void
     */
    public function boot(Router $router)
    {
        //

        parent::boot($router);
    }

    /**
     * Define the routes for the application.
     *
     * @param  \Mini\Routing\Router  $router
     * @return void
     */
    public function map(Router $router)
    {
        // Load the Routes for the API group.
        $router->group(array('prefix' => 'api', 'middleware' => 'api', 'namespace' => $this->namespace), function ($router)
        {
            require APPPATH .'Routes' .DS .'Api.php';
        });

        // Load the Routes for the WEB group.
        $router->group(array('middleware' => 'web', 'namespace' => $this->namespace), function ($router)
        {
            require APPPATH .'Routes' .DS .'Web.php';
        });
    }
}
