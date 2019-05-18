<?php

namespace System\Routing;

use System\Container\Container;
use System\Routing\RouteDependencyResolverTrait;

use Closure;


class ControllerDispatcher
{

    /**
     * Dispatch a request to a given controller and method.
     *
     * @param  \System\Routing\Controller  $controller
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function dispatch($controller, $method, array $parameters)
    {
        if (! method_exists($controller, 'callAction')) {
            return call_user_func_array(array($controller, $method), $parameters);
        }

        return $controller->callAction($method, $parameters);
    }

    /**
     * Get the middleware for the controller instance.
     *
     * @param  \System\Routing\Controller  $controller
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
