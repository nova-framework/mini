<?php

namespace Mini\View;

use Mini\Support\Contracts\RenderableInterface;

use BadMethodCallException;
use Exception;
use Throwable;


class View implements RenderableInterface
{
    /**
     * @var \Mini\View\Factory The View Factory instance.
     */
    protected $factory = null;

    /**
     * @var string The path to the View file on disk.
     */
    protected $path = null;

    /**
     * @var array Array of local data.
     */
    protected $data = array();


    /**
     * Constructor
     * @param Factory $factory
     * @param mixed $path
     * @param array $data
     */
    public function __construct(Factory $factory, $path, $data = array())
    {
        $this->factory = $factory;

        $this->path = $path;

        $this->data = is_array($data) ? $data : array($data);

    }

    /**
     * Get the string contents of the View.
     *
     * @param  \Closure  $callback
     * @return string
     */
    public function render()
    {
        // Extract the rendering variables.
        extract($this->gatherData(), EXTR_SKIP);

        // Start rendering.
        ob_start();

        try {
            include $this->getPath();
        }
        catch (Exception | Throwable $e) {
            ob_get_clean();

            throw $e;
        }

        return ltrim(ob_get_clean());
    }

    /**
     * Return all variables stored on local and shared data.
     *
     * @return array
     */
    protected function gatherData()
    {
        $data = array_merge($this->factory->getShared(), $this->getData());

        return array_map(function ($value)
        {
            return ($value instanceof View) ? $value->render() : $value;

        }, $data);
    }

    /**
     * Add a view instance to the view data.
     *
     * @param  string  $key
     * @param  string  $view
     * @param  array   $data
     * @return View
     */
    public function nest($key, $view, $data = array())
    {
        return $this->with($key, $this->factory->make($view, $data));
    }

    /**
     * Add a key / value pair to the shared view data.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return View
     */
    public function shares($key, $value)
    {
        $this->factory->share($key, $value);

        return $this;
    }

    /**
     * Add a key / value pair to the view data.
     *
     * Bound data will be available to the view as variables.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return View
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Get the inner data of the  View instance.
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the path currently being compiled.
     *
     * @param  string  $path
     * @return View
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Get the path currently being compiled.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Get the evaluated string content of the View.
     *
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->render();
        }
        catch (Exception | Throwable $e) {
            return '';
        }
    }

    /**
     * Magic Method for handling dynamic functions.
     *
     * @param  string  $method
     * @param  array   $params
     * @return \Mini\View\View|static|void
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $params)
    {
        // Add the support for the dynamic withX Methods.
        if (substr($method, 0, 4) == 'with') {
            $name = lcfirst(substr($method, 4));

            return $this->with($name, array_shift($params));
        }

        throw new BadMethodCallException("Method [$method] does not exist");
    }
}

