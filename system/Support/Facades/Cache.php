<?php

namespace System\Support\Facades;

use System\Support\Facades\Facade;


/**
 * @see \System\Cache\CacheManager
 * @see \System\Cache\Repository
 */
class Cache extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'cache'; }

}
