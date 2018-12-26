<?php

defined('DS') || define('DS', DIRECTORY_SEPARATOR);

//--------------------------------------------------------------------------
// Define the absolute paths for Application directories
//--------------------------------------------------------------------------

define('BASEPATH', realpath(__DIR__ .'/../') .DS);

define('WEBPATH', realpath(__DIR__) .DS);

define('APPPATH', BASEPATH .'app' .DS);

//--------------------------------------------------------------------------
// Load the Composer Autoloader
//--------------------------------------------------------------------------

require BASEPATH .'vendor' .DS .'autoload.php';

//--------------------------------------------------------------------------
// Create The Application
//--------------------------------------------------------------------------

$app = require APPPATH .'Platform' .DS .'Bootstrap.php';

//--------------------------------------------------------------------------
// Run The Application
//--------------------------------------------------------------------------

$app->run();
