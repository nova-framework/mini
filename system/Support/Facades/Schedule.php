<?php

namespace Mini\Support\Facades;

/**
 * @see \Mini\Console\Scheduling\Schedule
 */
class Schedule extends Facade
{

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'schedule'; }
}
