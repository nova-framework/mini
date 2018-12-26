<?php

namespace System\Routing;

use System\Http\RedirectResponse;
use System\Session\Store as SessionStore;


class Redirector
{
    /**
     * The URL generator instance.
     *
     * @var \System\Routing\UrlGenerator
     */
    protected $generator;

    /**
     * The session store instance.
     *
     * @var \System\Session\Store
     */
    protected $session;

    /**
     * Create a new Redirector instance.
     *
     * @param  \System\Routing\UrlGenerator  $generator
     * @return void
     */
    public function __construct(UrlGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Create a new redirect response to the "home" route.
     *
     * @param  int  $status
     * @return \System\Http\RedirectResponse
     */
    public function home($status = 302)
    {
        return $this->to($this->generator->route('home'), $status);
    }

    /**
     * Create a new redirect response to the previous location.
     *
     * @param  int    $status
     * @param  array  $headers
     * @return \System\Http\RedirectResponse
     */
    public function back($status = 302, $headers = array())
    {
        return $this->createRedirect($this->generator->previous(), $status, $headers);
    }

    /**
     * Create a new redirect response to the current URI.
     *
     * @param  int    $status
     * @param  array  $headers
     * @return \System\Http\RedirectResponse
     */
    public function refresh($status = 302, $headers = array())
    {
        return $this->to($this->generator->getRequest()->path(), $status, $headers);
    }

    /**
     * Create a new redirect response, while putting the current URL in the session.
     *
     * @param  string  $path
     * @param  int     $status
     * @param  array   $headers
     * @param  bool    $secure
     * @return \System\Http\RedirectResponse
     */
    public function guest($path, $status = 302, $headers = array(), $secure = null)
    {
        $this->session->put('url.intended', $this->generator->full());

        return $this->to($path, $status, $headers, $secure);
    }

    /**
     * Create a new redirect response to the previously intended location.
     *
     * @param  string  $default
     * @param  int     $status
     * @param  array   $headers
     * @param  bool    $secure
     * @return \System\Http\RedirectResponse
     */
    public function intended($default = '/', $status = 302, $headers = array(), $secure = null)
    {
        $path = $this->session->pull('url.intended', $default);

        return $this->to($path, $status, $headers, $secure);
    }

    /**
     * Create a new redirect response from the given path and arguments.
     *
     * @return \System\Http\RedirectResponse
     */
    public function url()
    {
        if (empty($parameters = func_get_args())) {
            return $this->to('/');
        }

        $path = array_shift($parameters);

        $result = preg_replace_callback('#\{(\d+)\}#', function ($matches) use ($parameters)
        {
            list ($value, $key) = $matches;

            return isset($parameters[$key]) ? $parameters[$key] : $value;

        }, $path);

        return $this->to($path);
    }

    /**
     * Create a new redirect response to the given path.
     *
     * @param  string  $path
     * @param  int     $status
     * @param  array   $headers
     * @param  bool    $secure
     * @return \System\Http\RedirectResponse
     */
    public function to($path, $status = 302, $headers = array(), $secure = null)
    {
        $path = $this->generator->to($path, array(), $secure);

        return $this->createRedirect($path, $status, $headers);
    }

    /**
     * Create a new redirect response to an external URL (no validation).
     *
     * @param  string  $path
     * @param  int     $status
     * @param  array   $headers
     * @return \System\Http\RedirectResponse
     */
    public function away($path, $status = 302, $headers = array())
    {
        return $this->createRedirect($path, $status, $headers);
    }

    /**
     * Create a new redirect response to the given HTTPS path.
     *
     * @param  string  $path
     * @param  int     $status
     * @param  array   $headers
     * @return \System\Http\RedirectResponse
     */
    public function secure($path, $status = 302, $headers = array())
    {
        return $this->to($path, $status, $headers, true);
    }

    /**
     * Create a new redirect response to a named route.
     *
     * @param  string  $route
     * @param  array   $parameters
     * @param  int     $status
     * @param  array   $headers
     * @return \Nova\Http\RedirectResponse
     */
    public function route($route, $parameters = array(), $status = 302, $headers = array())
    {
        $path = $this->generator->route($route, $parameters);

        return $this->to($path, $status, $headers);
    }

    /**
     * Create a new redirect response.
     *
     * @param  string  $path
     * @param  int     $status
     * @param  array   $headers
     * @return \System\Http\RedirectResponse
     */
    protected function createRedirect($path, $status, $headers)
    {
        $redirect = new RedirectResponse($path, $status, $headers);

        if (isset($this->session)) {
            $redirect->setSession($this->session);
        }

        $redirect->setRequest($this->generator->getRequest());

        return $redirect;
    }

    /**
     * Get the URL generator instance.
     *
     * @return  \System\Routing\UrlGenerator
     */
    public function getUrlGenerator()
    {
        return $this->generator;
    }

    /**
     * Set the active session store.
     *
     * @param  \System\Session\Store  $session
     * @return void
     */
    public function setSession(SessionStore $session)
    {
        $this->session = $session;
    }

}
