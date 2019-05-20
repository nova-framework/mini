<?php

namespace Mini\Container;

use Closure;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;


class CallbackCaller
{
    /**
     * Call the given Closure / class@method and inject its dependencies.
     *
     * @param  \Mini\Container\Container  $container
     * @param  callable|string  $callback
     * @param  array  $parameters
     * @param  string|null  $defaultMethod
     * @return mixed
     */
    public static function call($container, $callback, array $parameters = array(), $defaultMethod = null)
    {
        if (is_string($callback)) {
            list ($class, $method) = array_pad(
                explode('@', $callback, 2), 2, $defaultMethod
            );

            if (is_null($method)) {
                throw new InvalidArgumentException('Method not provided.');
            }

            $instance = $container->make($class);

            $callback = array($instance, $method);
        }

        $dependencies = static::getMethodDependencies(
            $container, $parameters, static::getCallReflector($callback)
        );

        return call_user_func_array($callback, $dependencies);
    }

    /**
     * Get all dependencies for a given method.
     *
     * @param  \Mini\Container\Container  $container
     * @param  array  $parameters
     * @param  \ReflectionFunctionAbstract  $reflector
     * @return array
     */
    protected static function getMethodDependencies($container, array $parameters, ReflectionFunctionAbstract $reflector)
    {
        $dependencies = array();

        foreach ($reflector->getParameters() as $parameter) {
            if (array_key_exists($name = $parameter->name, $parameters)) {
                $dependencies[] = $parameters[$name];

                unset($parameters[$name]);
            }

            // The dependency does not exists in parameters.
            else if (! is_null($class = $parameter->getClass())) {
                $dependencies[] = $container->make($class->name);
            }

            // The dependency does not reference a class.
            else if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            }
        }

        return array_merge($dependencies, $parameters);
    }

    /**
     * Get the proper reflection instance for the given callback.
     *
     * @param  callable|string  $callback
     * @return \ReflectionFunctionAbstract
     * @throws \InvalidArgumentException
     */
    protected static function getCallReflector($callback)
    {
        if ($callback instanceof Closure) {
            return new ReflectionFunction($callback);
        }

        list ($instance, $method) = $callback;

        return new ReflectionMethod($instance, $method);
    }
}
