<?php

namespace Mini\Routing;

use Mini\Container\Container;
use Mini\Http\Request;
use Mini\Http\Exception\HttpResponseException;
use Mini\Support\Str;

use Closure;
use LogicException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;


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
     * @var bool
     */
    protected $fallback = false;

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
     * Initialize the parmeters to mark the route as being matched.
     *
     * @return this
     */
    public function matched()
    {
        $this->parameters = array();

        return $this;
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
     * @param  \Mini\Http\Request  $request
     * @return mixed
     */
    public function run(Request $request)
    {
        if (! isset($this->container)) {
            $this->container = new Container();
        }

        $parameters = $this->getParameters();

        try {
            if (is_array($callback = $this->resolveActionCallback())) {
                return $this->callControllerCallback($callback, $parameters, $request);
            }
            
    	    $parameters = $this->resolveCallParameters(
                $parameters, new ReflectionFunction($callback)
	    );

	    return call_user_func_array($callback, $parameters);
        }
        catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }

    /**
     * Runs the controller callback and returns the response.
     *
     * @param  array  $callback
     * @param  array  $parameters
     * @param  \Mini\Http\Request  $request
     * @return mixed
     */
    protected function callControllerCallback(array $callback, array $parameters, Request $request)
    {
        list ($controller, $method) = $callback;

        $parameters = $this->resolveCallParameters(
            $parameters, new ReflectionMethod($controller, $method)
        );

        if (method_exists($controller, 'callAction')) {
            return $controller->callAction($method, $parameters, $request);
        }

        return call_user_func_array($callback, $parameters);
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
        foreach ($reflector->getParameters() as $offset => $parameter) {
            if (! is_null($class = $parameter->getClass())) {
                $instance = $this->container->make($class->name);

                array_splice($parameters, $offset, 0, array($instance));
            }
        }

        return $parameters;
    }

    /**
     * Resolve the route callback.
     *
     * @return \Closure|array
     * @throws \LogicException
     */
    protected function resolveActionCallback()
    {
        if (isset($this->callback)) {
            return $this->callback;
        }

        $callback = array_get($this->action, 'uses');

        if ($callback instanceof Closure) {
            return $this->callback = $callback;
        }

        //
        else if (! is_string($callback)) {
            throw new LogicException("The callback must be either a string or a Closure instance");
        }

        return $this->callback = $this->resolveStringCallback($callback);
    }

    /**
     * Resolve a controller callback.
     *
     * @param  string  $callback
     * @return array
     * @throws \LogicException
     */
    protected function resolveStringCallback($callback)
    {
        list ($className, $method) = explode('@', $callback, 2);

        if (! class_exists($className)) {
            throw new LogicException("Controller [{$className}] not found.");
        }

        //
        else if (! method_exists($controller = $this->container->make($className), $method)) {
            throw new LogicException("Controller [{$className}] has no method [{$method}]");
        }

        return array($controller, $method);
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

        if (is_array($callback = $this->resolveActionCallback())) {
            list ($controller, $method) = $callback;

            $middleware = array_merge($middleware, $controller->getMiddleware($method));
        }

        return $this->middleware = array_unique($middleware, SORT_REGULAR);
    }

    /**
     * Set the flag of fallback mode on the route.
     *
     * @param  bool  $value
     * @return $this
     */
    public function fallback()
    {
        $this->fallback = true;

        return $this;
    }

    /**
     * Returns true if the flag of fallback mode is set.
     *
     * @return bool
     */
    public function isFallback()
    {
        return $this->fallback;
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
        return array_get($this->action, 'as');
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
