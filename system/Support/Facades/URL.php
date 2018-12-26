<?php

namespace System\Support\Facades;

use System\Support\Facades\Facade;


/**
 * @see \System\Routing\UrlGenerator
 */
class URL extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'url'; }

}
