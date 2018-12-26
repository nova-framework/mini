<?php

use System\Config\Store as Config;
use System\Container\Container;
use System\Foundation\AliasLoader;
use System\Foundation\Application;

use App\Exceptions\Handler as ExceptionHandler;


//--------------------------------------------------------------------------
// Setup the Errors Reporting
//--------------------------------------------------------------------------

error_reporting(-1);

ini_set('display_errors', 'Off');

//--------------------------------------------------------------------------
// Set PHP Session Cache Limiter
//--------------------------------------------------------------------------

session_cache_limiter('');

//--------------------------------------------------------------------------
// Use Internally The UTF-8 Encoding
//--------------------------------------------------------------------------

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('utf-8');
}

//--------------------------------------------------------------------------
// Load Global Configuration
//--------------------------------------------------------------------------

require APPPATH .'Config.php';

//--------------------------------------------------------------------------
// Create The Application
//--------------------------------------------------------------------------

$app = new Application();

// Setup the Application instance.
$app->instance('app', $app);

$app->bindInstallPaths(array(
    'base'    => BASEPATH,
    'app'     => APPPATH,
    'storage' => STORAGE_PATH,
));


//--------------------------------------------------------------------------
// Set The Global Container Instance
//--------------------------------------------------------------------------

Container::setInstance($app);

//--------------------------------------------------------------------------
// Bind Important Interfaces
//--------------------------------------------------------------------------

$app->singleton(
    'System\Foundation\Exceptions\HandlerInterface', 'App\Platform\Exceptions\Handler'
);


//--------------------------------------------------------------------------
// Create The Config Instance
//--------------------------------------------------------------------------

$app->instance('config', $config = new Config());


//--------------------------------------------------------------------------
// Load The Platform Configuration
//--------------------------------------------------------------------------

foreach (glob(APPPATH .'Config/*.php') as $path) {
    if (is_readable($path)) {
        $key = lcfirst(pathinfo($path, PATHINFO_FILENAME));

        $config->set($key, require_once($path));
    }
}


//--------------------------------------------------------------------------
// Register Application Exception Handling
//--------------------------------------------------------------------------

$app->startExceptionHandling();


//--------------------------------------------------------------------------
// Set The Default Timezone
//--------------------------------------------------------------------------

date_default_timezone_set(
    $config->get('app.timezone', 'Europe/London')
);


//--------------------------------------------------------------------------
// Register The Service Providers
//--------------------------------------------------------------------------

$app->getProviderRepository()->load(
    $app, $providers = $config->get('app.providers', array())
);


//--------------------------------------------------------------------------
// Register The Alias Loader
//--------------------------------------------------------------------------

AliasLoader::getInstance(
    $config->get('app.aliases', array())

)->register();


//--------------------------------------------------------------------------
// Register Booted Start Files
//--------------------------------------------------------------------------

$app->booted(function() use ($app)
{


//--------------------------------------------------------------------------
// Load The Boootstrap Script
//--------------------------------------------------------------------------

$path = $app['path'] .DS .'Bootstrap.php';

if (is_readable($path)) require $path;

});


//--------------------------------------------------------------------------
// Return The Application
//--------------------------------------------------------------------------

return $app;
