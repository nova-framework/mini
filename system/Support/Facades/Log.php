<?php

namespace System\Support\Facades;


/**
* @see \System\Log\Writer
*/
class Log extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'log'; }
}
