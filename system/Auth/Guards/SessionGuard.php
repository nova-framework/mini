<?php

namespace System\Auth\Guards;

use System\Auth\Guard;
use System\Auth\UserInterface;
use System\Cookie\CookieJar;
use System\Events\Dispatcher;
use System\Http\Request;
use System\Hashing\HasherInterface;
use System\Session\Store as SessionStore;
use System\Support\Str;

use RuntimeException;


class SessionGuard extends Guard
{
    /**
     * The name of the Guard. Typically "session".
     *
     * Corresponds to driver name in authentication configuration.
     *
     * @var string
     */
    protected $name;

    /**
     * The user we last attempted to retrieve.
     *
     * @var \System\Auth\UserInterface
     */
    protected $lastAttempted;

    /**
     * Indicates if the User was authenticated via a recaller Cookie.
     *
     * @var bool
     */
    protected $viaRemember = false;

    /**
     * The session store used by the guard.
     *
     * @var \System\Session\Store
     */
    protected $session;

    /**
     * The Nova cookie creator service.
     *
     * @var \System\Cookie\CookieJar
     */
    protected $cookies;

    /**
     * The request instance.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * The hasher implementation.
     *
     * @var \System\Hashing\HasherInterface
     */
    protected $hasher;

    /**
     * The event dispatcher instance.
     *
     * @var \System\Events\Dispatcher
     */
    protected $events;

    /**
     * Indicates if the logout method has been called.
     *
     * @var bool
     */
    protected $loggedOut = false;

    /**
     * Indicates if a token user retrieval has been attempted.
     *
     * @var bool
     */
    protected $tokenRetrievalAttempted = false;


    /**
     * Create a new Authentication Guard instance.
     *
     * @param  string  $name
     * @param  string  $model
     * @param  \System\Session\Store  $session
     * @param  \System\Cookie\CookieJar  $cookies
     * @param  \System\Hashing\HasherInterface  $hasher
     * @param  \System\Events\Dispatcher  $events
     * @param  \System\Http\Request|null  $request
     * @return void
     */
    public function __construct($name, $model, SessionStore $session, CookieJar $cookies, HasherInterface $hasher, Dispatcher $events, Request $request = null)
    {
        parent::__construct($model);

        //
        $this->name    = $name;
        $this->session = $session;
        $this->cookies = $cookies;
        $this->hasher  = $hasher;
        $this->events  = $events;
        $this->request = $request;
    }

    /**
     * Get the authenticated user.
     *
     * @return \System\Auth\UserInterface|null
     */
    public function user()
    {
        if ($this->loggedOut) {
            return;
        }

        if (isset($this->user)) {
            return $this->user;
        }

        $id = $this->session->get($this->getName());

        //
        $user = null;

        if (! is_null($id)) {
            $user = $this->retrieveUserById($id);
        }

        if (is_null($user) && ! is_null($recaller = $this->getRecaller())) {
            $user = $this->getUserByRecaller($recaller);
        }

        return $this->user = $user;
    }


    /**
     * Get the ID for the currently authenticated User.
     *
     * @return int|null
     */
    public function id()
    {
        if (! $this->loggedOut) {
            $id = $this->session->get($this->getName(), $this->getRecallerId());

            if (is_null($id) && ! is_null($user = $this->user())) {
                $id = $user->getAuthIdentifier();
            }

            return $id;
        }
    }

    /**
     * Validate a user's credentials.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function validate(array $credentials = array())
    {
        return $this->attempt($credentials, false, false);
    }

    /**
     * Get a user by its recaller ID.
     *
     * @param  string $recaller
     * @return mixed
     */
    protected function getUserByRecaller($recaller)
    {
        if ($this->validRecaller($recaller) && ! $this->tokenRetrievalAttempted) {
            $this->tokenRetrievalAttempted = true;

            list ($id, $token) = explode('|', $recaller, 2);

            $this->viaRemember = ! is_null($user = $this->retrieveUserByToken($id, $token));

            return $user;
        }
    }

    /**
     * Get the decrypted Recaller cookie.
     *
     * @return string|null
     */
    protected function getRecaller()
    {
        $name = $this->getRecallerName();

        return $this->request->cookies->get($name);
    }

    /**
     * Get the user ID from the recaller Cookie.
     *
     * @return string
     */
    protected function getRecallerId()
    {
        if ($this->validRecaller($recaller = $this->getRecaller())) {
            $segments = explode('|', $recaller);

            return head($segments);
        }
    }

    /**
     * Determine if the recaller Cookie is in a valid format.
     *
     * @param  string $recaller
     * @return bool
     */
    protected function validRecaller($recaller)
    {
        if (is_string($recaller) && (strpos($recaller, '|') !== false)) {
            $segments = explode('|', $recaller);

            return (count($segments) == 2) && (trim($segments[0]) !== '') && (trim($segments[1]) !== '');
        }

        return false;
    }

    /**
     * Attempt to authenticate a User, using the given credentials.
     *
     * @param  array $credentials
     * @param  bool  $remember
     * @param  bool  $login
     * @return bool
     */
    public function attempt(array $credentials = array(), $remember = false, $login = true)
    {
        $this->fireAuthEvent('attempt', array($credentials, $remember, $login));

        $this->lastAttempted = $user = $this->retrieveUserByCredentials($credentials);

        if (! $this->hasValidCredentials($user, $credentials)) {
            return false;
        }

        // The user has valid credentials.
        else if ($login) {
            $this->login($user, $remember);
        }

        return true;
    }

    /**
     * Determine if the user matches the credentials.
     *
     * @param  mixed  $user
     * @param  array  $credentials
     * @return bool
     */
    protected function hasValidCredentials($user, $credentials)
    {
        $password = array_get($credentials, 'password');

        return ! is_null($user) && $this->hasher->check($password, $user->getAuthPassword());
    }

    /**
     * Register an authentication attempt event listener.
     *
     * @param  mixed  $callback
     * @return void
     */
    public function attempting($callback)
    {
        $this->events->listen('auth.attempt', $callback);
    }

    /**
     * Log a User in.
     *
     * @param  \System\Auth\UserInterface $user
     * @param  bool $remember
     * @return void
     */
    public function login(UserInterface $user, $remember = false)
    {
        $this->session->put($this->getName(), $user->getAuthIdentifier());

        $this->session->migrate(true);

        if ($remember) {
            $token = $user->getRememberToken();

            if (empty($token)) {
                $this->refreshRememberToken($user);
            }

            $this->queueRecallerCookie($user);
        }

        $this->fireAuthEvent('login', array($user, $remember));

        $this->setUser($user);
    }

    /**
     * Log the given user ID into the application.
     *
     * @param  mixed  $id
     * @param  bool   $remember
     * @return \System\Auth\UserInterface
     */
    public function loginUsingId($id, $remember = false)
    {
        $user = $this->retrieveUserById($id);

        $this->login($user, $remember);

        return $user;
    }

    /**
     * Queue the recaller cookie into the cookie jar.
     *
     * @param  \Auth\UserInterface  $user
     * @return void
     */
    protected function queueRecallerCookie(UserInterface $user)
    {
        $value = $user->getAuthIdentifier() .'|' .$user->getRememberToken();

        $this->cookies->queue(
            $this->cookies->forever($this->getRecallerName(), $value)
        );
    }

    /**
     * Log the user out.
     *
     * @return void
     */
    public function logout()
    {
        $user = $this->user();

        // Clear the User data from storage.
        $this->session->forget($this->getName());

        $this->cookies->queue(
            $this->cookies->forget($this->getRecallerName())
        );

        if (! is_null($user)) {
            $this->refreshRememberToken($user);
        }

        $this->fireAuthEvent('logout', array($user));

        // Reset the Guard information.
        $this->user = null;

        $this->loggedOut = true;
    }

    /**
     * Refresh the "Remember me" Token for the User.
     *
     * @param  \System\Auth\UserInterface $user
     * @return string
     */
    protected function refreshRememberToken(UserInterface $user)
    {
        $user->setRememberToken(Str::random(100));

        $user->save();
    }

    /**
     * Fire the given event for the guard.
     *
     * @param  string  $event
     * @param  array   $payload
     * @return void
     */
    protected function fireAuthEvent($event, array $payload)
    {
        $this->events->dispatch('auth.' .$event, $payload);
    }

    /**
     * Get the cookie creator instance used by the guard.
     *
     * @return \System\Cookie\CookieJar
     *
     * @throws \RuntimeException
     */
    public function getCookieJar()
    {
        return $this->cookies;
    }

    /**
     * Get the event dispatcher instance.
     *
     * @return \System\Events\Dispatcher
     */
    public function getDispatcher()
    {
        return $this->events;
    }

    /**
     * Get the session store used by the guard.
     *
     * @return \System\Session\Store
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Get the current request instance.
     *
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->request ?: Request::createFromGlobals();
    }

    /**
     * Set the current request instance.
     *
     * @param  \Symfony\Component\HttpFoundation\Request
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Get the last user we attempted to authenticate.
     *
     * @return \System\Auth\UserInterface
     */
    public function getLastAttempted()
    {
        return $this->lastAttempted;
    }

    /**
     * Get a unique identifier for the Auth session value.
     *
     * @return string
     */
    public function getName()
    {
        return sprintf('login_%s_%s', $this->name, sha1(get_class($this)));
    }

    /**
     * Get the name of the Cookie used to store the "recaller".
     *
     * @return string
     */
    public function getRecallerName()
    {
        return sprintf('%sremember_%s_%s', PREFIX, $this->name, sha1(get_class($this)));
    }

    /**
     * Determine if the User was authenticated via "remember me" Cookie.
     *
     * @return bool
     */
    public function viaRemember()
    {
        return $this->viaRemember;
    }
}
