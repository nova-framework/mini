#!/usr/bin/env php
<?php

//--------------------------------------------------------------------------
// Define The Application Paths
//--------------------------------------------------------------------------

defined('DS') || define('DS', DIRECTORY_SEPARATOR);

/** Define the absolute paths for configured directories. */
define('BASEPATH', realpath(__DIR__) .DS);

define('APPPATH', BASEPATH .'app' .DS);

define('WEBPATH', BASEPATH .'webroot' .DS);


//--------------------------------------------------------------------------
// Register The Auto Loader
//--------------------------------------------------------------------------

require BASEPATH .'vendor' .DS .'autoload.php';


//--------------------------------------------------------------------------
// Turn On The Lights
//--------------------------------------------------------------------------

$app = require_once APPPATH .'Platform' .DS .'Bootstrap.php';

$app->setRequestForConsoleEnvironment();

//--------------------------------------------------------------------------
// Register Booted Start Files
//--------------------------------------------------------------------------

$app->booted(function() use ($app)
{


//--------------------------------------------------------------------------
// Load The Boootstrap Script
//--------------------------------------------------------------------------

$path = $app['path'] .DS .'Console.php';

if (is_readable($path)) require $path;

});


//--------------------------------------------------------------------------
// Boot The Application
//--------------------------------------------------------------------------

$app->boot();
