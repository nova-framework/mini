<?php

namespace System\Support\Facades;

use System\Support\Facades\Facade;


/**
 * @see \System\Auth\AuthManager
 */
class Auth extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'auth'; }
}
