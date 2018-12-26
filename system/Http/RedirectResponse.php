<?php

namespace System\Http;

use System\Session\Store as SessionStore;
use System\Support\Contracts\MessageProviderInterface;
use System\Support\MessageBag;
use System\Support\ViewErrorBag;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;


class RedirectResponse extends SymfonyRedirectResponse
{
    /**
     * The request instance.
     *
     * @var \System\Http\Request
     */
    protected $request;

    /**
     * The session store implementation.
     *
     * @var \System\Session\Store
     */
    protected $session;

    /**
     * Set a header on the Response.
     *
     * @param  string  $key
     * @param  string  $value
     * @param  bool  $replace
     * @return \System\Http\RedirectResponse
     */
    public function header($key, $value, $replace = true)
    {
        $this->headers->set($key, $value, $replace);

        return $this;
    }

    /**
     * Flash a piece of data to the session.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return \System\Http\RedirectResponse
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) $this->with($k, $v);
        } else {
            $this->session->flash($key, $value);
        }

        return $this;
    }

    /**
     * Add a cookie to the response.
     *
     * @param  \Symfony\Component\HttpFoundation\Cookie  $cookie
     * @return \System\Http\RedirectResponse
     */
    public function withCookie(Cookie $cookie)
    {
        $this->headers->setCookie($cookie);

        return $this;
    }

    /**
     * Flash an array of input to the session.
     *
     * @param  array  $input
     * @return \System\Http\RedirectResponse
     */
    public function withInput(array $input = null)
    {
        $input = $input ?: $this->request->input();

        $this->session->flashInput($input);

        return $this;
    }

    /**
     * Flash an array of input to the session.
     *
     * @param  dynamic  string
     * @return \System\Http\RedirectResponse
     */
    public function onlyInput()
    {
        return $this->withInput($this->request->only(func_get_args()));
    }

    /**
     * Flash an array of input to the session.
     *
     * @param  dynamic  string
     * @return \System\Http\RedirectResponse
     */
    public function exceptInput()
    {
        return $this->withInput($this->request->except(func_get_args()));
    }

    /**
     * Flash a container of errors to the session.
     *
     * @param  \System\Support\Contracts\MessageProviderInterface|array  $provider
     * @return \System\Http\RedirectResponse
     */
    public function withErrors($provider, $key = 'default')
    {
        $value = $this->parseErrors($provider);

        $this->session->flash(
            'errors', $this->session->get('errors', new ViewErrorBag)->put($key, $value)
        );

        return $this;
    }

    /**
     * Parse the given errors into an appropriate value.
     *
     * @param  \Nova\Support\Contracts\MessageProviderInterface|array  $provider
     * @return \Nova\Support\MessageBag
     */
    protected function parseErrors($provider)
    {
        if ($provider instanceof MessageBag) {
            return $provider;
        } else if ($provider instanceof MessageProviderInterface) {
            return $provider->getMessageBag();
        }

        return new MessageBag((array) $provider);
    }

    /**
     * Get the request instance.
     *
     * @return  \System\Http\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Set the request instance.
     *
     * @param  \System\Http\Request  $request
     * @return void
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get the session store implementation.
     *
     * @return \System\Session\Store
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Set the session store implementation.
     *
     * @param  \System\Session\Store  $store
     * @return void
     */
    public function setSession(SessionStore $session)
    {
        $this->session = $session;
    }

    /**
     * Dynamically bind flash data in the session.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return void
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (starts_with($method, 'with')) {
            return $this->with(snake_case(substr($method, 4)), $parameters[0]);
        }

        throw new \BadMethodCallException("Method [$method] does not exist on Redirect.");
    }

}
