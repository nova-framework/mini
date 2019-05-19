<?php

namespace System\Routing;

use ReflectionFunctionAbstract;
use ReflectionMethod;


trait RouteDependencyResolverTrait
{

    /**
     * Resolve the given method's type-hinted dependencies.
     *
     * @param  array  $parameters
     * @param  \ReflectionFunctionAbstract  $reflector
     * @return array
     */
    protected function resolveMethodDependencies(array $parameters, ReflectionFunctionAbstract $reflector)
    {
        $instanceCount = 0;

        $values = array_values($parameters);

        foreach ($reflector->getParameters() as $key => $parameter) {
            if (! is_null($class = $parameter->getClass())) {
                $instance = $this->container->make($class->getName());

                $instanceCount++;

                $this->spliceIntoParameters($parameters, $key, $instance);
            }

            // The parameter does not reference a class.
            else if (! isset($values[$key - $instanceCount]) && $parameter->isDefaultValueAvailable()) {
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
