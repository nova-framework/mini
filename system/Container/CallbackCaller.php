<?php

namespace Mini\Container;

use Mini\Container\Container;
use Mini\Support\Str;

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
     * @param  \Mini\Container \Container $container
     * @param  callable|string  $callback
     * @param  array  $parameters
     * @param  string|null  $defaultMethod
     * @throws \InvalidArgumentException
     * @return mixed
     */
    public static function call(Container $container, $callback, array $parameters = array(), $defaultMethod = null)
    {
        if (is_string($callback)) {
            $callback = static::resolveStringCallback($container, $callback, $defaultMethod);
        }

        if ($callback instanceof Closure) {
            $reflector = new ReflectionFunction($callback);
        }

        //
        else if (is_array($callback)) {
            $reflector = new ReflectionMethod($callback[0], $callback[1]);
        }  else {
            throw new InvalidArgumentException('Invalid callback provided.');
        }

        return call_user_func_array(
            $callback, static::getMethodDependencies($container, $parameters, $reflector)
        );
    }

    /**
     * Resolve a string callback.
     *
     * @param  \Mini\Container \Container $container
     * @param  string  $callback
     * @param  string|null  $defaultMethod
     * @throws \InvalidArgumentException
     * @return array
     */
    protected static function resolveStringCallback(Container $container, $callback, $defaultMethod = null)
    {
        list ($className, $method) = Str::parseCallback($callback, $defaultMethod);

        if (empty($method) || ! class_exists($className)) {
            throw new InvalidArgumentException('Invalid callback provided.');
        }

        return array(
            $container->make($className), $method
        );
    }

    /**
     * Get all dependencies for a given method.
     *
     * @param  \Mini\Container \Container $container
     * @param  array  $parameters
     * @param  \ReflectionFunctionAbstract  $reflector
     * @return array
     */
    protected static function getMethodDependencies(Container $container, array $parameters, ReflectionFunctionAbstract $reflector)
    {
        $dependencies = array();

        foreach ($reflector->getParameters() as $parameter) {
            if (array_key_exists($name = $parameter->getName(), $parameters)) {
                $dependencies[] = $parameters[$name];

                unset($parameters[$name]);
            }

            // The dependency does not exists in parameters.
            else if (! is_null($class = $parameter->getClass())) {
                $className = $class->getName();

                $dependencies[] = $container->make($className);
            }

            // The dependency does not reference a class.
            else if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            }
        }

        return array_merge($dependencies, $parameters);
    }
}
