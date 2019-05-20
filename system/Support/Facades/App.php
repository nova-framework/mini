<?php

namespace Mini\Support\Facades;


/**
* @see \Mini\Foundation\Application
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
