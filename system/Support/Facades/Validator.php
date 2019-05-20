<?php

namespace Mini\Support\Facades;


/**
 * @see \Mini\Validation\Factory
 * @see \Mini\Validation\Validator
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
