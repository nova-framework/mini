<?php

namespace Mini\Routing;

use Mini\Container\Container;
use Mini\Http\Request;

use Closure;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;


class CallbackCaller
{
    /**
     * The Router instance.
     *
     * @var \Mini\Container\Container
     */
    protected $container;


    /**
     * Create a new Route instance.
     *
     * @param  \Mini\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Runs the route callback and returns the response.
     *
     * @param  \Closure|array  $callback
     * @param  array  $parameters
     * @param  \Mini\Http\Request  $request
     * @return mixed
     */
    public function call($callback, array $parameters, Request $request)
    {
        if ($callback instanceof Closure) {
            $parameters = $this->resolveCallParameters(
                $parameters, new ReflectionFunction($callback)
            );

            return call_user_func_array($callback, $parameters);
        }

        extract($callback);

        $parameters = $this->resolveCallParameters(
            $parameters, new ReflectionMethod($controller, $method)
        );

        if (method_exists($controller, 'callAction')) {
            return $controller->callAction($method, $parameters, $request);
        }

        return call_user_func_array(array($controller, $method), $parameters);
    }

    /**
     * Resolve the given method's type-hinted dependencies.
     *
     * @param  array  $parameters
     * @param  \ReflectionFunctionAbstract  $reflector
     * @return array
     */
    protected function resolveCallParameters(array $parameters, ReflectionFunctionAbstract $reflector)
    {
        $count = 0;

        $values = array_values($parameters);

        foreach ($reflector->getParameters() as $key => $parameter) {
            if (! is_null($class = $parameter->getClass())) {
                $className = $class->getName();

                $this->spliceIntoParameters($parameters, $key, $this->container->make($className));

                $count++;
            }

            //
            else if (! isset($values[$key - $count]) && $parameter->isDefaultValueAvailable()) {
                $this->spliceIntoParameters($parameters, $key, $parameter->getDefaultValue());
            }
        }

        return array_values($parameters);
    }

    /**
     * Splice the given value into the parameter list.
     *
     * @param  array  $parameters
     * @param  string  $offset
     * @param  mixed  $value
     * @return void
     */
    protected function spliceIntoParameters(array &$parameters, $offset, $value)
    {
        array_splice($parameters, $offset, 0, array($value));
    }
}
