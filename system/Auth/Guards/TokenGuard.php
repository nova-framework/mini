<?php

namespace Mini\Auth\Guards;

use Mini\Auth\Guard;
use Mini\Http\Request;


class TokenGuard extends Guard
{
    /**
     * The request instance.
     *
     * @var \Mini\Http\Request
     */
    protected $request;

    /**
     * The name of the field on the request containing the API token.
     *
     * @var string
     */
    protected $inputKey = 'api_token';

    /**
     * The name of the token "column" in persistent storage.
     *
     * @var string
     */
    protected $storageKey = 'api_token';


    /**
     * Create a new authentication guard.
     *
     * @param  string  $model
     * @param  \Mini\Http\Request|null  $request
     * @return void
     */
    public function __construct($model, Request $request = null)
    {
        parent::__construct($model);

        //
        $this->request = $request;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return \Mini\Auth\UserInterface|null
     */
    public function user()
    {
        if (isset($this->user)) {
            return $this->user;
        }

        $user = null;

        if (! empty($token = $this->getTokenForRequest())) {
            $user = $this->retrieveUserByCredentials(array(
                $this->storageKey => $token
            ));
        }

        return $this->user = $user;
    }

    /**
     * Get the token for the current request.
     *
     * @return string
     */
    protected function getTokenForRequest()
    {
        $token = $this->request->input($this->inputKey);

        if (empty($token)) {
            $token = $this->request->bearerToken();
        }

        if (empty($token)) {
            $token = $this->request->getPassword();
        }

        return $token;
    }

    /**
     * Validate a user's credentials.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function validate(array $credentials = array())
    {
        if (empty($token = array_get($credentials, $this->inputKey))) {
            return false;
        }

        $user = $this->retrieveUserByCredentials(array(
            $this->storageKey => $token
        ));

        return ! is_null($user);
    }

    /**
     * Set the current request instance.
     *
     * @param  \Mini\Http\Request  $request
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }
}
