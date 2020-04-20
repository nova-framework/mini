<?php

namespace Mini\View;

use Mini\Support\Str;

use BadMethodCallException;


class Factory
{
    /**
     * @var array Array of shared data.
     */
    protected $shared = array();


    /**
     * Get a View instance.
     *
     * @param mixed $view
     * @param array $data
     *
     * @return \Mini\View\View
     * @throws \BadMethodCallException
     */
    public function make($view, $data = array())
    {
        if (! is_readable($path = $this->getViewPath($view))) {
            throw new BadMethodCallException("File path [$path] does not exist");
        }

        return new View($this, $path, $data);
    }

    /**
     * Add a key / value pair to the shared view data.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function share($key, $value = null)
    {
        return $this->shared[$key] = $value;
    }

    /**
     * Create a View instance and return its rendered content.
     *
     * @return string
     */
    public function fetch($view, $data = array())
    {
        return $this->make($view, $data)->render();
    }

    /**
     * Get the rendered contents of a partial from a loop.
     *
     * @param  string  $view
     * @param  array   $items
     * @param  string  $iterator
     * @param  string  $empty
     * @return string
     */
    public function renderEach($view, array $items, $iterator, $empty = 'raw|')
    {
        if (empty($items)) {
            return Str::startsWith($empty, 'raw|') ? substr($empty, 4) : $this->fetch($empty);
        }

        $results = array();

        foreach ($items as $key => $value) {
            $results[] = $this->fetch($view, array('key' => $key, $iterator => $value));
        }

        return implode("\n", $results);
    }

    /**
     * Returns true if the specified View exists.
     *
     * @param mixed $view
     *
     * @return bool
     */
    public function exists($view)
    {
        $path = $this->getViewPath($view);

        return is_readable($path);
    }

    /**
     * Get the view path.
     *
     * @return array
     */
    protected function getViewPath($view)
    {
        return str_replace('/', DS, APPPATH ."Views/${view}.php");
    }

    /**
     * Get all of the shared data for the Factory.
     *
     * @return array
     */
    public function getShared()
    {
        return $this->shared;
    }
}

