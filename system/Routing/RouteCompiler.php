<?php

namespace Mini\Routing;

use DomainException;
use LogicException;


class RouteCompiler
{
    /**
     * @var \Mini\Routing\Route
     */
    protected $route;


    /**
     * Create a new Route Compiler instance.
     *
     * @param  \Mini\Routing\Route $route
     * @return void
     */
    public function __construct(Route $route)
    {
        $this->route = $route;
    }

    /**
     * Compile the inner Route pattern.
     *
     * @return string
     * @throws \LogicException|\DomainException
     */
    public function compile()
    {
        $path = with($route = $this->getRoute())->getPath();

        $patterns = $route->getPatterns();

        //
        $optionals = 0;

        $variables = array();

        $pattern = preg_replace_callback('#/\{(.*?)(\?)?\}#', function ($matches) use ($path, $patterns, &$optionals, &$variables)
        {
            list (, $name, $optional) = array_pad($matches, 3, false);

            if (in_array($name, $variables)) {
                throw new LogicException("Route pattern [{$path}] cannot reference variable name [{$name}] more than once.");
            } else if (strlen($name) > 32) {
                throw new DomainException("Variable name [{$name}] cannot be longer than 32 characters in route pattern [{$path}].");
            } else if (preg_match('/^\d/', $name) === 1) {
                throw new DomainException("Variable name [{$name}] cannot start with a digit in route pattern [{$path}].");
            }

            $variables[] = $name;

            //
            $pattern = array_get($patterns, $name, '[^/]+');

            if ($optional) {
                $optionals++;

                return sprintf('(?:/(?P<%s>%s)', $name, $pattern);
            }

            //
            else if ($optionals > 0) {
                throw new LogicException("Route pattern [{$path}] cannot reference standard variable [{$name}] after optionals.");
            }

            return sprintf('/(?P<%s>%s)', $name, $pattern);

        }, $path);

        return sprintf('#^%s%s$#s', $pattern, str_repeat(')?', $optionals));
    }

    /**
     * Get the inner route.
     *
     * @return array
     */
    public function getRoute()
    {
        return $this->route;
    }
}
