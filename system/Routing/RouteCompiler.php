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
     * @var string
     */
    protected $path;

    /**
     * @var array
     */
    protected $patterns = array();


    /**
     * Create a new Route Compiler instance.
     *
     * @param  string $path
     * @param  array  $patterns
     * @return void
     */
    public function __construct(Route $route)
    {
        $this->route = $route;

        //
        $this->path = $route->getPath();

        $this->patterns = $route->getPatterns();
    }

    /**
     * Compile the inner Route pattern.
     *
     * @return string
     * @throws \LogicException|\DomainException
     */
    public function compile()
    {
        $optionals = 0;

        $variables = array();

        //
        $path = $this->getPath();

        $pattern = preg_replace_callback('#/\{(.*?)(\?)?\}#', function ($matches) use ($path, &$optionals, &$variables)
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
            $pattern = array_get($this->patterns, $name, '[^/]+');

            $regex = sprintf('/(?P<%s>%s)', $name, $pattern);

            if (! $optional) {
                if ($optionals > 0) {
                    throw new LogicException("Route pattern [{$path}] cannot reference standard variable [{$name}] after optionals.");
                }

                return $regex;
            }

            $optionals++;

            return '(?:' .$regex;

        }, $path);

        return sprintf('#^%s%s$#s', $pattern, str_repeat(')?', $optionals));
    }

    /**
     * Get the inner Route instance.
     *
     * @return \Mini\Routing\Route
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * Get the URI associated with the inner route.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Get the patterns defined the inner route.
     *
     * @return array
     */
    public function getPatterns()
    {
        return $this->patterns;
    }
}
