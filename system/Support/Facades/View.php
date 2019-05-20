<?php

namespace Mini\Support\Facades;


/**
 * @see \Mini\View\Factory
 * @see \Mini\View\View
 */
class View extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'view'; }
}
