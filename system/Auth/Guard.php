<?php

namespace Mini\Auth;

use Mini\Support\Str;

use RuntimeException;


abstract class Guard
{
    /**
     * The currently authenticated user.
     *
     * @var \Mini\Auth\UserInterface
     */
    protected $user;

    /**
     * The User Model implementation.
     *
     * @var string
     */
    protected $model;

    /**
     * The User Model instance.
     *
     * @var string
     */
    protected $modelInstance;


    /**
     * Create a new authentication guard.
     *
     * @param  string|null  $model
     * @return void
     */
    public function __construct($model = null)
    {
        $this->model = $model;
    }

    /**
     * Determine if the current user is authenticated.
     *
     * @return \Mini\Auth\UserInterface
     *
     * @throws \Mini\Auth\AuthenticationException
     */
    public function authenticate()
    {
        if (! is_null($user = $this->user())) {
            return $user;
        }

        throw new AuthenticationException;
    }

    /**
     * Determine if the current user is authenticated.
     *
     * @return bool
     */
    public function check()
    {
        return ! is_null($this->user());
    }

    /**
     * Determine if the current user is a guest.
     *
     * @return bool
     */
    public function guest()
    {
        return ! $this->check();
    }

    /**
     * Get the ID for the currently authenticated user.
     *
     * @return int|null
     */
    public function id()
    {
        if (! is_null($user = $this->user())) {
            return $user->getAuthIdentifier();
        }
    }

    /**
     * Get the currently authenticated user.
     *
     * @return \Mini\Auth\UserInterface|null
     */
    abstract public function user();

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array $credentials
     * @return \Mini\Auth\UserInterface|null
     */
    public function retrieveUserByCredentials(array $credentials)
    {
        $query = $this->createModel()->newQuery();

        foreach ($credentials as $key => $value) {
            if (! Str::contains($key, 'password')) {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    /**
     * Retrieve a user by the given id.
     *
     * @param  mixed $identifier
     * @return \Mini\Auth\UserInterface|null
     */
    public function retrieveUserById($identifier)
    {
        $model = $this->createModel();

        return $model->newQuery()->find($identifier);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return \Mini\Auth\UserInterface|null
     */
    public function retrieveUserByToken($identifier, $token)
    {
        $model = $this->createModel();

        return $model->newQuery()
            ->where($model->getKeyName(), $identifier)
            ->where($model->getRememberTokenName(), $token)
            ->first();
    }

    /**
     * Create a new instance of the model.
     *
     * @return \Nova\Database\ORM\Model
     */
    public function createModel()
    {
        if (isset($this->modelInstance)) {
            return $this->modelInstance;
        }

        $className = '\\' .ltrim($this->model, '\\');

        return $this->modelInstance = new $className;
    }

    /**
     * Return the currently cached user of the application.
     *
     * @return \Mini\Auth\UserInterface|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set the current user.
     *
     * @param  \Mini\Auth\UserInterface  $user
     * @return $this
     */
    public function setUser(UserInterface $user)
    {
        $this->user = $user;

        return $this;
    }
}
