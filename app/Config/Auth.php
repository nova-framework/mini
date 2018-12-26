<?php


return array(

    /*
    |--------------------------------------------------------------------------
    | Authentication Default Guard
    |--------------------------------------------------------------------------
    */

    'default' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Supported: "session", "token"
    |
    */

    'guards' => array(
        'web' => array(
            'driver' => 'session',
            'model'  => 'App\Models\User',

            'paths' => array(
                'authorize' => 'login',
                'dashboard' => 'dashboard',
            ),
        ),
        'api' => array(
            'driver' => 'token',
            'model'  => 'App\Models\User',
        ),
    ),

);
