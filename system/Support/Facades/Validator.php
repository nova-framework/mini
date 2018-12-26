<?php

namespace System\Support\Facades;


/**
 * @see \System\Validation\Factory
 * @see \System\Validation\Validator
 */
class Validator extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'validator'; }
}
