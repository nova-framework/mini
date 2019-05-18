<?php

namespace System\Routing;

use System\Http\Request;

use Countable;


class RouteCollection implements Countable
{
    /**
     * An array of the routes keyed by method.
     *
     * @var array
     */
    protected $routes = array(
        'GET'     => array(),
        'POST'    => array(),
        'PUT'     => array(),
        'DELETE'  => array(),
        'PATCH'   => array(),
        'HEAD'    => array(),
        'OPTIONS' => array(),
    );

    /**
     * An flattened array of all of the routes.
     *
     * @var array
     */
    protected $allRoutes = array();

    /**
     * A look-up table of routes by their names.
     *
     * @var array
     */
    protected $namedRoutes = array();


    /**
     * Add a Route instance to the collection.
     *
     * @param  \System\Routing\Route  $route
     * @return \System\Routing\Route
     */
    public function add(Route $route)
    {
        $path = $route->getPath();

        foreach ($route->getMethods() as $method) {
            $this->routes[$method][$path] = $route;
        }

        $this->allRoutes[] = $route;

        //
        $action = $route->getAction();

        if (! empty($name = array_get($action, 'as'))) {
            $this->namedRoutes[$name] = $route;
        }

        return $route;
    }

    /**
     * Find the Route which matches the given Request.
     *
     * @param  \System\Http\Request  $request
     * @return \System\Routing|Route|null
     */
    public function match(Request $request)
    {
        $method = $request->method();

        $path = rawurldecode('/' .trim($request->path(), '/'));

        //
        $routes = array_get($this->routes, $method, array());

        if (! is_null($route = array_get($routes, $path))) {
            return $route;
        }

        return array_first($routes, function ($uri, $route) use ($path, $method)
        {
            return $route->matches($path, $method);
        });
    }

    /**
     * Determine if the route collection contains a given named route.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasNamedRoute($name)
    {
        return array_has($this->namedRoutes, $name);
    }

    /**
     * Get a route instance by its name.
     *
     * @param  string  $name
     * @return \Nova\Routing\Route|null
     */
    public function getByName($name)
    {
        return array_get($this->namedRoutes, $name);
    }

    /**
     * Get all registered routes.
     *
     * @return array
     */
    public function getRoutes()
    {
        return $this->allRoutes;
    }

    /**
     * Get the registered named routes.
     *
     * @return array
     */
    public function getNamedRoutes()
    {
        return $this->namedRoutes;
    }

    /**
     * Count the number of items in the collection.
     *
     * @return int
     */
    public function count()
    {
        return count($this->allRoutes);
    }
}
