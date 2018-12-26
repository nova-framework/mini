<?php

namespace System\Routing;

use System\Container\Container;
use System\Http\Request;
use System\Http\Response;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BadMethodCallException;
use Closure;
use LogicException;


class Router
{
    /**
     * The Container instance.
     *
     * @var \System\Container
     */
    protected $container;

    /**
     * An array of registered routes.
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
     * All of the named routes and URI pairs.
     *
     * @var array
     */
    protected $namedRoutes = array();

    /**
     * All of the short-hand keys for middlewares.
     *
     * @var array
     */
    protected $middleware = array();

    /**
     * All of the middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = array();

    /**
     * The route group attribute stack.
     *
     * @var array
     */
    protected $groupStack = array();

    /**
     * The global parameter patterns.
     *
     * @var array
     */
    protected $patterns = array();

    /**
     * The current route being dispatched.
     *
     * @var \System\Routing\Route|null
     */
    protected $currentRoute;


    /**
     * Create a new Router instance.
     *
     * @param  \Nova\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container, array $middleware, array $middlewareGroups)
    {
        $this->container = $container;

        $this->middleware = $middleware;

        $this->middlewareGroups = $middlewareGroups;
    }

    /**
     * Register a new route responding to all verbs.
     *
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return void
     */
    public function any($route, $action)
    {
        return $this->match(
            array('GET', 'POST', 'PUT', 'DELETE', 'PATCH'), $route, $action
        );
    }

    /**
     * Create a route group with shared attributes.
     *
     * @param  array     $attributes
     * @param  \Closure  $callback
     * @return void
     */
    public function group(array $attributes, Closure $callback)
    {
        if (is_string($middleware = array_get($attributes, 'middleware', array()))) {
            $attributes['middleware'] = explode('|', $middleware);
        }

        if (! empty($this->groupStack)) {
            $attributes = static::mergeGroup($attributes, last($this->groupStack));
        }

        $this->groupStack[] = $attributes;

        call_user_func($callback, $this);

        array_pop($this->groupStack);
    }

    /**
     * Merge the given group attributes.
     *
     * @param  array  $new
     * @param  array  $old
     * @return array
     */
    protected static function mergeGroup($new, $old)
    {
        $namespace = array_get($old, 'namespace');

        if (isset($new['namespace'])) {
            $namespace = trim($namespace, '\\') .'\\' .trim($new['namespace'], '\\');
        }

        $new['namespace'] = $namespace;

        //
        $prefix = array_get($old, 'prefix');

        if (isset($new['prefix'])) {
            $prefix = trim($prefix, '/') .'/' .trim($new['prefix'], '/');
        }

        $new['prefix'] = $prefix;

        $new['where'] = array_merge(
            array_get($old, 'where', array()),
            array_get($new, 'where', array())
        );

        return array_merge_recursive(
            array_except($old, array('namespace', 'prefix', 'where')), $new
        );
    }

    /**
     * Add a route to the underlying route lists.
     *
     * @param  array|string  $methods
     * @param  string  $path
     * @param  \Closure|array|string  $action
     * @return \Nova\Routing\Route
     */
    public function match(array $methods, $path, $action)
    {
        $methods = array_map('strtoupper', $methods);

        if (in_array('GET', $methods) && ! in_array('HEAD', $methods)) {
            $methods[] = 'HEAD';
        }

        if (! is_array($action)) {
            $action = array('uses' => $action);
        }

        //
        else if (! isset($action['uses'])) {
            $action['uses'] = $this->findActionClosure($action);
        }

        if (is_string($middleware = array_get($action, 'middleware', array()))) {
            $action['middleware'] = explode('|', $middleware);
        }

        if (! empty($this->groupStack)) {
            $action = static::mergeGroup($action, $group = last($this->groupStack));

            if (is_string($uses = $action['uses']) && isset($group['namespace'])) {
                $action['uses'] = $group['namespace'] .'\\' .$uses;
            }

            if (isset($group['prefix'])) {
                $path = trim($group['prefix'], '/') .'/' .trim($path, '/');
            }
        }

        $path = '/' .trim($path, '/');

        //
        $route = new Route($methods, $path, $action, $this->patterns);

        foreach ($methods as $method) {
            $this->routes[$method][$path] = $route;
        }

        if (! empty($name = array_get($action, 'as'))) {
            $this->namedRoutes[$name] = $route;
        }

        return $route->setContainer($this->container);
    }

    /**
     * Find the Closure in an action array.
     *
     * @param  array  $action
     * @return \Closure
     */
    protected function findActionClosure(array $action)
    {
        return array_first($action, function ($key, $value)
        {
            return is_callable($value) && is_numeric($key);
        });
    }

    /**
     * Dispatch the given Request instance.
     *
     * @param  \System\Http\Request  $request
     * @return \System\Http\Response
     */
    public function dispatch(Request $request)
    {
        $this->currentRoute = $route = $this->findRoute($request);

        if (is_null($route)) {
            throw new NotFoundHttpException('Page not found');
        }

        $request->setRouteResolver(function ()
        {
            return $this->currentRoute;
        });

        $pipeline = new Pipeline($this->container, $this->gatherMiddleware($route));

        return $pipeline->handle($request, function ($request) use ($route)
        {
            $response = $route->run();

            return $this->prepareResponse($request, $response);
        });
    }

    /**
     * Find the Route which matches the given Request.
     *
     * @param  \System\Http\Request  $request
     * @return \System\Routing|Route|null
     */
    protected function findRoute(Request $request)
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
     * Gather the middleware from the specified route action.
     *
     * @param \System\Routing\Route $route
     * @return array
     */
    public function gatherMiddleware(Route $route)
    {
        $middleware = $this->resolveMiddleware($route->middleware());

        return array_unique($middleware, SORT_REGULAR);
    }

    /**
     * Parse the middleware group and format it for usage.
     *
     * @param  array  $middleware
     * @return array
     */
    protected function resolveMiddleware(array $middleware)
    {
        $results = array();

        foreach ($middleware as $name) {
            if (is_null($group = array_get($this->middlewareGroups, $name))) {
                array_push($results, $this->parseMiddleware($name));

                continue;
            }

            $results = array_merge($results, $this->resolveMiddleware($group));
        }

        return $results;
    }

    /**
     * Parse the middleware and format it for usage.
     *
     * @param string $name
     * @return mixed
     */
    protected function parseMiddleware($name)
    {
        list ($name, $parameters) = array_pad(explode(':', $name, 2), 2, null);

        $callable = array_get($this->middleware, $name, $name);

        if (empty($parameters)) {
            return $callable;
        } else if (is_string($callable)) {
            return $callable .':' .$parameters;
        }

        $parameters = explode(',', $parameters);

        return function ($passable, $stack) use ($callable, $parameters)
        {
            $parameters = array_merge(array($passable, $stack), $parameters);

            return call_user_func_array($callable, $parameters);
        };
    }

    /**
     * Registers a new route middleware.
     *
     * @param string $name
     * @param mixed $middleware
     * @return Router
     */
    public function middleware($name, $middleware)
    {
        $this->middleware[$name] = $middleware;

        return $this;
    }

    /**
     * Registers a new route pattern.
     *
     * @param string $key
     * @param string $pattern
     * @return void
     */
    public function pattern($key, $pattern)
    {
        $this->patterns[$key] = $pattern;
    }

    /**
     * Prepares a response.
     *
     * @param mixed $response
     * @return \System\Http\Response
     */
    public function prepareResponse($request, $response)
    {
        if (! $response instanceof SymfonyResponse) {
            $response = new Response($response);
        }

        return $response->prepare($request);
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
     * Get the currently dispatched route action, if any.
     *
     * @return array|null
     */
    public function getCurrentRoute()
    {
        return $this->currentRoute;
    }

    /**
     * Dynamically handle calls into the Router instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (array_key_exists($key = strtoupper($method), $this->routes)) {
            array_unshift($parameters, array($key));

            return call_user_func_array(array($this, 'match'), $parameters);
        }

        throw new BadMethodCallException("Method [${method}] does not exist.");
    }
}
