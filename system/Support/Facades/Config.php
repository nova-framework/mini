<?php

namespace Mini\Support\Facades;


/**
* @see \Mini\Config\Store
*/
class Config extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'config'; }
}
