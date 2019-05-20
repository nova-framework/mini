<?php

namespace Mini\Support\Facades;


/**
* @see \Mini\Translation\Translator
*/
class Lang extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'translator'; }
}
