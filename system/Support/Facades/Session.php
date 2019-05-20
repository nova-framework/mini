<?php

namespace Mini\Support\Facades;


/**
* @see \Mini\Session\Store
*/
class Session extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'session'; }
}
