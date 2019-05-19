<?php

namespace System\Routing;

use System\Container\Container;
use System\Http\Request;
use System\Support\Str;

use Closure;
use DomainException;
use LogicException;
use ReflectionFunction;
use UnexpectedValueException;


class Route
{
    use RouteDependencyResolverTrait;

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
     * @param  array|string  $methods
     * @param  string $path
     * @param  array  $action
     * @return void
     */
    public function __construct($methods, $path, array $action)
    {
        $this->methods = (array) $methods;

        $this->path   = $path;
        $this->action = $action;

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

        $pattern = with(new RouteCompiler($this))->compile();

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
     * Run the given action callback.
     *
     * @return mixed
     */
    public function run()
    {
        $parameters = $this->getParameters();

        if (is_array($callback = $this->resolveCallback())) {
            $dispatcher = new ControllerDispatcher($this->container);

            return $dispatcher->dispatch($callback, $parameters);
        }

        return call_user_func_array($callback, $this->resolveMethodDependencies(
            $parameters, new ReflectionFunction($callback)
        ));
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

        // The action callback must be either a Closure instance or a string.
        else if (! is_string($callback)) {
            throw new LogicException("Invalid route action");
        }

        list ($className, $method) = array_pad(explode('@', $callback, 2), 2, null);

        if (is_null($method) || ! class_exists($className)) {
            throw new LogicException("Invalid route action: [{$callback}]");
        }

        // We will create a Controller instance, then we will check if its method exists.
        else if (! method_exists($controller = $this->container->make($className), $method)) {
            throw new LogicException("Controller [{$className}] has no method [${method}].");
        }

        return $this->callback = array($controller, $method);
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
            list ($controller, $method) = $callback;

            $middleware = array_merge(
                $middleware, ControllerDispatcher::getMiddleware($controller, $method)
            );
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
