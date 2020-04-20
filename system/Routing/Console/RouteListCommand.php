<?php

namespace Mini\Routing\Console;

use Mini\Http\Request;
use Mini\Routing\Route;
use Mini\Routing\Router;
use Mini\Console\Command;
use Mini\Support\Arr;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;

use Closure;


class RouteListCommand extends Command
{
    /**
    * The console command name.
    *
    * @var string
    */
    protected $name = 'routes';

    /**
    * The console command description.
    *
    * @var string
    */
    protected $description = 'List all registered routes';

    /**
    * An array of all the registered routes.
    *
    * @var \Mini\Routing\RouteCollection
    */
    protected $routes;

    /**
    * The table headers for the command.
    *
    * @var array
    */
    protected $headers = array(
        'Method', 'URI', 'Name', 'Action', 'Middleware'
    );


    /**
    * Create a new route command instance.
    *
    * @param  \Mini\Routing\Router  $router
    * @return void
    */
    public function __construct(Router $router)
    {
        parent::__construct();

        //
        $this->routes = $router->getRoutes();
    }

    /**
    * Execute the console command.
    *
    * @return void
    */
    public function handle()
    {
        if (count($this->routes) == 0) {
            return $this->error("Your application doesn't have any routes.");
        }

        $routes = $this->getRoutes();

        //
        $table = new Table($this->output);

        $table->setHeaders($this->headers)->setRows($routes);

        $table->render($this->getOutput());
    }

    /**
    * Compile the routes into a displayable format.
    *
    * @return array
    */
    protected function getRoutes()
    {
        $routes = $this->routes->getRoutes();

        //
        $fallbacks = array();

        foreach ($routes as $key => $route) {
            if ($route->isFallback()) {
                $fallbacks[$key] = $route;

                unset($routes[$key]);
            }
        }

        return array_map(function ($route)
        {
            return $this->getRouteInformation($route);

        }, array_merge($routes, $fallbacks));
    }

    /**
    * Get the route information for a given route.
    *
    * @param  string  $name
    * @param  \Mini\Routing\Route  $route
    * @return array
    */
    protected function getRouteInformation(Route $route)
    {
        $methods = implode('|', $route->getMethods());

        $middleware = implode(', ', $this->getMiddleware($route));

        return $this->filterRoute(array(
            'method'     => $methods,
            'uri'        => $route->getPath(),
            'name'       => $route->getName(),
            'action'     => $route->getActionName(),
            'middleware' => $middleware
        ));
    }

    /**
     * Get before filters.
     *
     * @param  \Mini\Routing\Route  $route
     * @return string
     */
    protected function getMiddleware($route)
    {
        return array_map(function ($middleware)
        {
            return ($middleware instanceof Closure) ? 'Closure' : $middleware;

        }, $route->getMiddleware());
    }

    /**
    * Filter the route by URI and / or name.
    *
    * @param  array  $route
    * @return array|null
    */
    protected function filterRoute(array $route)
    {
        if (($this->option('name') && ! str_contains($route['name'], $this->option('name'))) ||
            $this->option('path') && ! str_contains($route['uri'], $this->option('path'))) {
            return null;
        }

        return $route;
    }

    /**
    * Get the console command options.
    *
    * @return array
    */
    protected function getOptions()
    {
        return array(
            array('name', null, InputOption::VALUE_OPTIONAL, 'Filter the routes by name.'),
            array('path', null, InputOption::VALUE_OPTIONAL, 'Filter the routes by path.'),
        );
    }

}
