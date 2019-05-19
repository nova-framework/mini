<?php

namespace System\Routing;

use System\Container\Container;

use ReflectionMethod;


class ControllerDispatcher
{
    use RouteDependencyResolverTrait;

    /**
     * The container instance.
     *
     * @var \System\Container\Container
     */
    protected $container;


    /**
     * Create a new controller dispatcher instance.
     *
     * @param  \System\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Dispatch a request to a given controller callback.
     *
     * @param  array  $callback
     * @param  array  $parameters
     * @return mixed
     */
    public function dispatch(array $callback, array $parameters)
    {
        list ($controller, $method) = $callback;

        $parameters = $this->resolveMethodDependencies(
            $parameters, new ReflectionMethod($controller, $method)
        );

        if (method_exists($controller, $callerMethod = 'callAction')) {
            $callback = array($controller, $callerMethod);

            $parameters = $this->resolveMethodDependencies(
                array($method, $parameters), new ReflectionMethod($controller, $callerMethod)
            );
        }

        return call_user_func_array($callback, $parameters);
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

        $results = array();

        foreach ($controller->getMiddleware() as $middleware => $options) {
            if (static::methodExcludedByOptions($method, $options)) {
                continue;
            }

            $results[] = $middleware;
        }

        return $results;
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
