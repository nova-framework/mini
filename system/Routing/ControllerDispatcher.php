<?php

namespace Mini\Routing;

use Mini\Container\Container;

use ReflectionMethod;


class ControllerDispatcher
{
    use RouteDependencyResolverTrait;

    /**
     * The container instance.
     *
     * @var \Mini\Container\Container
     */
    protected $container;


    /**
     * Create a new controller dispatcher instance.
     *
     * @param  \Mini\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Dispatch a request to a given controller callback.
     *
     * @param  \Mini\Routing\Route  $route
     * @param  mixed  $controller
     * @param  string  $method
     * @return mixed
     */
    public function dispatch(Route $route, $controller, $method)
    {
        $parameters = $this->resolveMethodDependencies(
            $route->getParameters(), new ReflectionMethod($controller, $method)
        );

        if (method_exists($controller, 'callAction')) {
            return $controller->callAction($method, $parameters);
        }

        return call_user_func_array(array($controller, $method), $parameters);
    }

    /**
     * Get the middleware for the controller instance.
     *
     * @param  mixed  $controller
     * @param  string  $method
     * @return array
     */
    public static function getMiddleware($controller, $method)
    {
        if (! method_exists($controller, 'getMiddleware')) {
            return array();
        }

        $results = array_filter($controller->getMiddleware(), function ($options) use ($method)
        {
            return ! static::methodExcludedByOptions($method, $options);
        });

        return array_keys($results);
    }

    /**
     * Determine if the given options exclude a particular method.
     *
     * @param  string  $method
     * @param  array  $options
     * @return bool
     */
    public static function methodExcludedByOptions($method, array $options)
    {
        return ((isset($options['only']) && ! in_array($method, (array) $options['only'])) ||
                (isset($options['except']) && in_array($method, (array) $options['except'])));
    }
}
