<?php

namespace System\Support\Facades;


/**
* @see \System\Foundation\Application
*/
class App extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'app'; }
}
