<?php

namespace Mini\Routing;

use BadMethodCallException;


class Controller
{
    /**
     * The middleware registered on the controller.
     *
     * @var array
     */
    protected $middleware = array();


    /**
     * Call an action on the controller.
     *
     * @param  string  $middleware
     * @param  array   $options
     * @return void
     */
    public function callAction($method, array $parameters)
    {
        return call_user_func_array(array($this, $method), $parameters);
    }

    /**
     * Register middleware on the controller.
     *
     * @param  string  $middleware
     * @param  array   $options
     * @return void
     */
    public function middleware($middleware, array $options = array())
    {
        $this->middleware[$middleware] = $options;
    }

    /**
     * Get the middleware assigned to the controller.
     *
     * @param  string  $method
     * @return array
     */
    public function getMiddleware($method)
    {
        $results = array_filter($this->middleware, function ($options) use ($method)
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
    protected static function methodExcludedByOptions($method, array $options)
    {
        if (isset($options['only']) && ! in_array($method, (array) $options['only'])) {
            return true;
        }

        return isset($options['except']) && in_array($method, (array) $options['except']);
    }

    /**
     * Dynamically handle calls into the Controller instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        throw new BadMethodCallException("Method [${method}] does not exist.");
    }
}
