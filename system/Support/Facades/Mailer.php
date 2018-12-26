<?php

namespace System\Support\Facades;

use System\Support\Facades\Facade;


/**
 * @see \System\Mail\Mailer
 */
class Mailer extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'mailer'; }

}
