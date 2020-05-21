<?php

namespace Mini\Routing;

use Mini\Container\Container;
use Mini\Http\Request;
use Mini\Http\Response;
use Mini\Support\Str;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

use BadMethodCallException;
use Closure;
use LogicException;


class Router
{
    /**
     * The Container instance.
     *
     * @var \Mini\Container\Container
     */
    protected $container;

    /**
     * The route collection instance.
     *
     * @var \Mini\Routing\RouteCollection
     */
    protected $routes;

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
     * @var \Mini\Routing\Route|null
     */
    protected $currentRoute;

    /**
     * An array of registered routes.
     *
     * @var array
     */
    public static $verbs = array('GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS');


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

        //
        $this->routes = new RouteCollection();
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
        if (! empty($namespace = array_get($old, 'namespace'))) {
            if (isset($new['namespace'])) {
                $namespace = trim($namespace, '\\') .'\\' .trim($new['namespace'], '\\');
            }

            $new['namespace'] = $namespace;
        }

        if (! empty($prefix = array_get($old, 'prefix'))) {
            if (isset($new['prefix'])) {
                $prefix = trim($prefix, '/') .'/' .trim($new['prefix'], '/');
            }

            $new['prefix'] = $prefix;
        }

        $new['where'] = array_merge(
            array_get($old, 'where', array()),
            array_get($new, 'where', array())
        );

        return array_merge_recursive(
            array_except($old, array('namespace', 'prefix', 'where')), $new
        );
    }

    /**
     * Register a new route responding to all verbs.
     *
     * @param  string  $path
     * @param  \Closure|array|string  $action
     * @return \Mini\Routing\Route
     */
    public function any($path, $action)
    {
        $methods = array('GET', 'POST', 'PUT', 'DELETE', 'PATCH');

        return $this->addRoute($methods, $path, $action);
    }

    /**
     * Register a new fallback route.
     *
     * @param  \Closure|array|string  $action
     * @return \Mini\Routing\Route
     */
    public function fallback($action)
    {
        $methods = array('GET', 'HEAD');

        return $this->addRoute($methods, "{fallback}", $action)
            ->where('fallback', '(.*)')
            ->fallback();
    }

    /**
     * Register a new route responding to the specified verbs.
     *
     * @param  array|string  $methods
     * @param  string  $path
     * @param  mixed  $action
     * @return \Mini\Routing\Route
     * @throws \LogicException
     */
    public function match($methods, $path, $action)
    {
        $methods = array_map('strtoupper', (array) $methods);

        return $this->addRoute($methods, $path, $action);
    }

    /**
     * Create and add a new Route instance to the routes collection.
     *
     * @param  array  $methods
     * @param  string  $path
     * @param  mixed  $action
     * @return \Mini\Routing\Route
     */
    protected function addRoute(array $methods, $path, $action)
    {
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

            if (is_string($uses = $action['uses']) && ! empty($namespace = array_get($group, 'namespace'))) {
                $action['uses'] = trim($namespace, '\\') .'\\' .trim($uses, '\\');
            }

            if (! empty($prefix = array_get($group, 'prefix'))) {
                $path = trim($prefix, '/') .'/' .trim($path, '/');
            }
        }

        $path = '/' .trim($path, '/');

        //
        $patterns = array_merge($this->patterns, array_get($action, 'where', array()));

        $route = with(new Route($methods, $path, $action))->where($patterns);

        return $this->routes->add($route)->setContainer($this->container);
    }

    /**
     * Find the Closure in an action array.
     *
     * @param  array  $action
     * @return \Closure|null
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
     * @param  \Mini\Http\Request  $request
     * @return \Mini\Http\Response
     */
    public function dispatch(Request $request)
    {
        $route = $this->findRoute($request);

        $request->setRouteResolver(function () use ($route)
        {
            return $route;
        });

        $pipeline = new Pipeline(
            $this->container, $this->gatherMiddleware($route)
        );

        $response = $pipeline->dispatch($request, function ($request) use ($route)
        {
            return $this->prepareResponse($request, $route->run());
        });

        return $this->prepareResponse($request, $response);
    }

    /**
     * Find the Route which matches the given Request.
     *
     * @param  \Mini\Http\Request  $request
     * @return \Mini\Routing|Route|null
     */
    protected function findRoute(Request $request)
    {
        return $this->currentRoute = $this->routes->match($request);
    }

    /**
     * Gather the middleware from the specified route action.
     *
     * @param \Mini\Routing\Route $route
     * @return array
     */
    public function gatherMiddleware(Route $route)
    {
        return $this->resolveMiddleware(
            $route->getMiddleware()
        );
    }

    /**
     * Resolve the given middleware.
     *
     * @param array $middleware
     * @return array
     */
    protected function resolveMiddleware(array $middleware)
    {
        $results = array();

        foreach ($middleware as $name) {
            if (! empty($group = array_get($this->middlewareGroups, $name))) {
                $results = array_merge($results, $this->resolveMiddleware($group));
            } else {
                $results[] = $this->parseMiddleware($name);
            }
        }

        return array_unique($results, SORT_REGULAR);
    }

    /**
     * Parse the middleware and format it for usage.
     *
     * @param string $name
     * @return mixed
     */
    protected function parseMiddleware($name)
    {
        list ($name, $payload) = array_pad(explode(':', $name, 2), 2, '');

        //
        $callable = array_get($this->middleware, $name, $name);

        if (empty($payload)) {
            return $callable;
        } else if (is_string($callable)) {
            return $callable .':' .$payload;
        }

        return function ($passable, $stack) use ($callable, $payload)
        {
            $parameters = array_merge(
                array($passable, $stack), explode(',', $payload)
            );

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
     * @param \Mini\Http\Request $request
     * @param mixed $response
     * @return \Mini\Http\Response
     */
    public function prepareResponse($request, $response)
    {
        if (! $response instanceof SymfonyResponse) {
            $response = new Response($response);
        }

        return $response->prepare($request);
    }

    /**
     * Get the currently dispatched Route, if any.
     *
     * @return \Mini\Routing\Route|null
     */
    public function getCurrentRoute()
    {
        return $this->currentRoute;
    }

    /**
     * Get the inner Route Collection instance.
     *
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Get the defined route patterns.
     *
     * @return array
     */
    public function getPatterns()
    {
        return $this->patterns;
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
        if (in_array($key = strtoupper($method), static::$verbs)) {
            array_unshift($parameters, (array) $key);

            return call_user_func_array(array($this, 'addRoute'), $parameters);
        }

        throw new BadMethodCallException("Method [${method}] does not exist.");
    }
}
