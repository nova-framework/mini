<?php

namespace System\Routing;

use System\Container\Container;
use System\Http\Request;
use System\Support\Str;

use Closure;
use DomainException;
use LogicException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use UnexpectedValueException;


class Route
{
    /**
     * The Container instance.
     *
     * @var \System\Container\Container
     */
    protected $container;

    /**
     * @var array
     */
    protected $methods;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var array
     */
    protected $action;

    /**
     * @var array
     */
    protected $patterns = array();

    /**
     * @var array
     */
    protected $parameters = array();

    /**
     * The callback to be executed.
     *
     * @var \Closure|array
     */
    protected $callback;


    /**
     * Create a new Route instance.
     *
     * @param  array  $methods
     * @param  string $path
     * @param  array  $action
     * @param  array  $patterns
     * @param  \System\Container\Container $container
     * @return void
     */
    public function __construct(array $methods, $path, array $action, array $patterns = array())
    {
        $this->methods = $methods;
        $this->path    = $path;
        $this->action  = $action;

        $this->patterns = array_merge($patterns, array_get($action, 'where', array()));
    }

    /**
     * Matches the Route pattern against the given path.
     *
     * @param  string  $path
     * @param  string  $method
     * @return bool
     */
    public function matches($path, $method = 'ANY')
    {
        $pattern = $this->compilePattern();

        if (($method !== 'ANY') && ! in_array($method, $this->methods)) {
            return false;
        }

        //
        else if (preg_match($pattern, $path, $matches) !== 1) {
            return false;
        }

        $this->parameters = array_filter($matches, function ($value, $key)
        {
            return is_string($key) && ! empty($value);

        }, ARRAY_FILTER_USE_BOTH);

        return true;
    }

    /**
     * Compile a Route pattern.
     *
     * @return string
     * @throws \DomainException|\LogicException
     */
    protected function compilePattern()
    {
        $optionals = 0;

        $variables = array();

        //
        $path = $this->getPath();

        $pattern = preg_replace_callback('#/\{(.*?)(\?)?\}#', function ($matches) use ($path, &$optionals, &$variables)
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
            $pattern = array_get($this->patterns, $name, '[^/]+');

            $result = sprintf('/(?P<%s>%s)', $name, $pattern);

            if ($optional) {
                $optionals++;

                return '(?:' .$result;
            }

            //
            else if ($optionals > 0) {
                throw new LogicException("Route pattern [{$path}] cannot reference variable [{$name}] after optional variables.");
            }

            return $result;

        }, $path);

        return '#^' .$pattern .str_repeat(')?', $optionals) .'$#s';
    }

    /**
     * Run the given action callback.
     *
     * @return mixed
     */
    public function run()
    {
        $callback = $this->resolveCallback();

        if ($callback instanceof Closure) {
            $reflector = new ReflectionFunction($callback);

            return call_user_func_array(
                $callback, $this->resolveCallParameters($reflector)
            );
        }

        extract($callback);

        $parameters = $this->resolveCallParameters(
            new ReflectionMethod($controller, $method)
        );

        return $controller->callAction($method, $parameters);
    }

    /**
     * Resolve the route callback.
     *
     * @return \Closure|array
     * @throws \UnexpectedValueException|\LogicException
     */
    protected function resolveCallback()
    {
        if (isset($this->callback)) {
            return $this->callback;
        }

        $callback = $this->action['uses'];

        if ($callback instanceof Closure) {
            return $this->callback = $callback;
        }

        //
        else if (! Str::contains($callback, '@')) {
            throw new UnexpectedValueException("Invalid route action: [{$callback}]");
        }

        list ($className, $method) = explode('@', $callback);

        if (! class_exists($className)) {
            throw new LogicException("Controller [{$className}] not found.");
        }

        //
        else if (! method_exists($controller = $this->container->make($className), $method)) {
            throw new LogicException("Controller [{$className}] has no method [${method}].");
        }

        return $this->callback = compact('controller', 'method');
    }

    /**
     * Resolve the method parameters.
     *
     * @param \ReflectionFunctionAbstract $reflector
     * @return array
     */
    protected function resolveCallParameters(ReflectionFunctionAbstract $reflector)
    {
        $parameters = $this->getParameters();

        foreach ($reflector->getParameters() as $key => $parameter) {
            if (! is_null($class = $parameter->getClass())) {
                $instance = $this->container->make($class->getName());

                array_splice($parameters, $key, 0, array($instance));
            }
        }

        return $parameters;
    }

    /**
     * Set a regular expression requirement on the route.
     *
     * @param  array|string  $name
     * @param  string  $pattern
     * @return $this
     */
    public function where($name, $pattern = null)
    {
        $patterns = is_array($name) ? $name : array($name => $pattern);

        foreach ($patterns as $name => $pattern) {
            $this->patterns[$name] = $pattern;
        }

        return $this;
    }

    /**
     * Get or set the middlewares attached to the route.
     *
     * @param  array|string|null $middleware
     * @return $this|array
     */
    public function middleware($middleware = null)
    {
        if (is_null($middleware)) {
            return $this->resolveMiddleware();
        }

        //
        else if (is_string($middleware)) {
            $middleware = array($middleware);
        }

        $middleware = array_merge(
            array_get($this->action, 'middleware', array()), $middleware
        );

        $this->action['middleware'] = array_unique($middleware, SORT_REGULAR);

        return $this;
    }

    /**
     * Resolve the route Middleware.
     *
     * @return array
     */
    protected function resolveMiddleware()
    {
        $middleware = array_get($this->action, 'middleware', array());

        if (is_array($callback = $this->resolveCallback())) {
            extract($callback);

            $middleware = array_merge($middleware, $controller->gatherMiddleware($method));
        }

        return array_unique($middleware, SORT_REGULAR);
    }

    /**
     * Get the patterns of the route instance.
     *
     * @return string
     */
    public function getPatterns()
    {
        return $this->patterns;
    }

    /**
     * Get the URI associated with the route.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Get the HTTP verbs the route responds to.
     *
     * @return array
     */
    public function getMethods()
    {
        return $this->methods;
    }

    /**
     * Get the key / value list of parameters for the route.
     *
     * @return array
     */
    public function getParameters()
    {
        return array_map(function ($value)
        {
            return is_string($value) ? rawurldecode($value) : $value;

        }, $this->parameters);
    }

    /**
     * Get a given parameter from the route.
     *
     * @param  string  $name
     * @param  mixed   $default
     * @return string
     */
    public function parameter($name = null, $default = null)
    {
        return array_get($this->getParameters(), $name, $default);
    }

    /**
     * Get the name of the route instance.
     *
     * @return string
     */
    public function getName()
    {
        array_get($this->action, 'as');
    }

    /**
     * Get the action name for the route.
     *
     * @return string
     */
    public function getActionName()
    {
        if (is_string($callback = $this->action['uses'])) {
            return $callback;
        }

        return 'Closure';
    }

    /**
     * Get the action array for the route.
     *
     * @return array
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Set the container instance on the route.
     *
     * @param  \Nova\Container\Container  $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Dynamically access route parameters.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->parameter($key);
    }
}
