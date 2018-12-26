<?php

namespace System\Support\Facades;


/**
* @see \System\Encryption\Encrypter
*/
class Crypt extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'encrypter'; }
}
