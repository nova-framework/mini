<?php

namespace Mini\Support\Facades;


/**
 * @see \Mini\Http\Request
 */
class Request extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'request'; }
}
