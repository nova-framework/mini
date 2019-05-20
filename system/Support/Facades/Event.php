<?php

namespace Mini\Support\Facades;


/**
* @see \Mini\Events\Dispatcher
*/
class Event extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'events'; }
}
