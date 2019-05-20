<?php

namespace Mini\Support\Facades;


/**
* @see \Mini\Hashing\BcryptHasher
*/
class Hash extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'hash'; }
}
