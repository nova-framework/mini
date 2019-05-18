<?php

namespace System\Routing;

use DomainException;
use LogicException;


class RouteCompiler
{
    /**
     * The route instance.
     *
     * @var \System\Routing\Route
     */
    protected $route;

    /**
     * Create a new Route compiler instance.
     *
     * @param  \System\Routing\Route  $route
     * @return void
     */
    public function __construct(Route $route)
    {
        $this->route = $route;
    }

    /**
     * Compile the Route pattern.
     *
     * @return string
     * @throws \DomainException|\LogicException
     */
    public function compile()
    {
        $optionals = 0;

        $variables = array();

        //
        $patterns = $this->route->getPatterns();

        $path = $this->route->getPath();

        $pattern = preg_replace_callback('#/\{(.*?)(\?)?\}#', function ($matches) use ($path, $patterns, &$optionals, &$variables)
        {
            list (, $name, $optional) = array_pad($matches, 3, false);

            if (preg_match('/^\d/', $name) === 1) {
                throw new DomainException("Variable name [{$name}] cannot start with a digit in route pattern [{$path}].");
            } else if (in_array($name, $variables)) {
                throw new LogicException("Route pattern [{$path}] cannot reference variable name [{$name}] more than once.");
            } else if (strlen($name) > 32) {
                throw new DomainException("Variable name [{$name}] cannot be longer than 32 characters in route pattern [{$path}].");
            }

            $variables[] = $name;

            //
            $pattern = array_get($patterns, $name, '[^/]+');

            if ($optional) {
                $optionals++;

                return sprintf('(?:/(?P<%s>%s)', $name, $pattern);
            }

            // The variable is not optional.
            else if ($optionals > 0) {
                throw new LogicException("Route pattern [{$path}] cannot reference variable [{$name}] after optional variables.");
            }

            return sprintf('/(?P<%s>%s)', $name, $pattern);

        }, $path);

        return sprintf('#^%s%s$#s', $pattern, str_repeat(')?', $optionals));
    }

    /**
     * Get the inner Route instance.
     *
     * @return \System\Routing\Route
     */
    public function getRoute()
    {
        return $this->route;
    }
}
