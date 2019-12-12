<?php

namespace Mini\Routing;

use Mini\Container\Container;
use Mini\Http\Request;

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
     * The Router instance.
     *
     * @var \Mini\Container\Container
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
    protected $parameters;

    /**
     * The callback to be executed.
     *
     * @var \Closure|array
     */
    protected $callback;

    /**
     * The computed gathered middleware.
     *
     * @var array|null
     */
    protected $middleware;


    /**
     * Create a new Route instance.
     *
     * @param  array|string  $methods
     * @param  string $path
     * @param  array  $action
     * @return void
     */
    public function __construct($methods, $path, array $action)
    {
        $this->path = $path;

        $this->action = $action;

        $this->methods = (array) $methods;

        if (in_array('GET', $this->methods) && ! in_array('HEAD', $this->methods)) {
            $this->methods[] = 'HEAD';
        }
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
        if (($method !== 'ANY') && ! in_array($method, $this->methods)) {
            return false;
        }

        $pattern = static::compilePattern($this->path, $this->patterns);

        if (preg_match($pattern, $path, $matches) !== 1) {
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
     * @param  string  $path
     * @param  array  $patterns
     * @return string
     * @throws \LogicException
     */
    protected static function compilePattern($path, $patterns)
    {
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
                throw new LogicException("Route pattern [{$path}] cannot reference variable [{$name}] after optionals.");
            }

            return sprintf('/(?P<%s>%s)', $name, $pattern);

        }, $path);

        return sprintf('#^%s%s$#s', $pattern, str_repeat(')?', $optionals));
    }

    /**
     * Run the given action callback.
     *
     * @return mixed
     */
    public function run()
    {
        $parameters = $this->getParameters();

        if (! is_array($callback = $this->resolveCallback())) {
            $reflector = new ReflectionFunction($callback);

            return call_user_func_array(
                $callback, $this->resolveCallParameters($parameters, $reflector)
            );
        }

        extract($callback);

        $parameters = $this->resolveCallParameters(
            $parameters, new ReflectionMethod($controller, $method)
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

        $callback = array_get($this->action, 'uses');

        if ($callback instanceof Closure) {
            return $this->callback = $callback;
        }

        list ($className, $method) = array_pad(explode('@', $callback, 2), 2, '');

        if (! class_exists($className) || empty($method)) {
            throw new LogicException("Invalid route action: [{$callback}]");
        }

        $controller = $this->container->make($className);

        return $this->callback = compact('controller', 'method');
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

        $this->patterns = array_merge($this->patterns, $patterns);

        return $this;
    }

    /**
     * Get the middlewares attached to the route.
     *
     * @return array
     */
    public function getMiddleware()
    {
        if (isset($this->middleware)) {
            return $this->middleware;
        }

        $middleware = array_get($this->action, 'middleware', array());

        if (is_array($callback = $this->resolveCallback())) {
            extract($callback);

            $middleware = array_merge(
                $middleware, $controller->getMiddleware($method)
            );
        }

        return $this->middleware = array_unique($middleware, SORT_REGULAR);
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
        if (! isset($this->parameters)) {
            throw new LogicException("Route is not bound.");
        }

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
        $parameters = $this->getParameters();

        return array_get($parameters, $name, $default);
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
        $callback = array_get($this->action, 'uses');

        return is_string($callback) ? $callback : 'Closure';
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
     * @param  \Mini\Container\Container  $container
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
