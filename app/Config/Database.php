<?php

return array(
    // The PDO Fetch Style.
    'fetch' => PDO::FETCH_CLASS,

    // The Default Database Connection Name.
    'default' => 'mysql',

    // The Database Connections.
    'connections' => array(
        'mysql' => array(
            'driver'    => 'mysql',
            'hostname'  => 'localhost',
            'database'  => 'mini',
            'username'  => 'mini',
            'password'  => 'password',
            'prefix'    => PREFIX,
            'charset'   => 'utf8',
            'collation' => 'utf8_general_ci',
        ),
    ),
);
