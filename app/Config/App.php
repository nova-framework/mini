<?php

return array(

    /**
     * Debug Mode
     */
    'debug' => true, // When enabled the actual PHP errors will be shown.

    /**
     * The Website URL.
     */
    'url' => 'http://www.miniframework.local/',

    /**
     * Website Name.
     */
    'name' => 'Mini MVC Framework',

    /**
     * The default Timezone for your website.
     * http://www.php.net/manual/en/timezones.php
     */
    'timezone' => 'Europe/Bucharest',

    /*
     * Application Default Locale.
     */
    'locale' => 'en',

    /*
     * Application Fallback Locale.
     */
    'fallbackLocale' => 'en',

    /**
     * The Encryption Key.
     * This page can be used to generate key - http://novaframework.com/token-generator
     */
    'key' => 'SomeRandomStringThere_1234567890',

    /**
     * The Platform's Middleware stack.
     */
    'middleware' => array(
        'App\Middleware\DispatchAssetFiles',
    ),

    /**
     * The Platform's route Middleware Groups.
     */
    'middlewareGroups' => array(
        'web' => array(
            'App\Middleware\HandleProfiling',
            'App\Middleware\EncryptCookies',
            'Mini\Cookie\Middleware\AddQueuedCookiesToResponse',
            'Mini\Session\Middleware\StartSession',
            'Mini\View\Middleware\ShareErrorsFromSession',
        ),
        'api' => array(
            'throttle:60,1',
        ),
    ),

    /**
     * The Platform's route Middleware.
     */
    'routeMiddleware' => array(
        'auth'     => 'Mini\Auth\Middleware\Authenticate',
        'guest'    => 'App\Middleware\RedirectIfAuthenticated',
        'throttle' => 'Mini\Routing\Middleware\ThrottleRequests',
        'csrf'     => 'App\Middleware\VerifyCsrfToken',
    ),

    /**
     * The registered Service Providers.
     */
    'providers' => array(
        'Mini\Auth\AuthServiceProvider',
        'Mini\Database\DatabaseServiceProvider',
        'Mini\Routing\RoutingServiceProvider',
        'Mini\Cookie\CookieServiceProvider',
        'Mini\Session\SessionServiceProvider',
        'Mini\Encryption\EncryptionServiceProvider',
        'Mini\Filesystem\FilesystemServiceProvider',
        'Mini\Hashing\HashServiceProvider',
        'Mini\Cache\CacheServiceProvider',
        'Mini\Mail\MailServiceProvider',
        'Mini\Pagination\PaginationServiceProvider',
        'Mini\Translation\TranslationServiceProvider',
        'Mini\Validation\ValidationServiceProvider',
        'Mini\View\ViewServiceProvider',

        // The Forge Providers.
        'Mini\Cache\ConsoleServiceProvider',
        'Mini\Foundation\ConsoleServiceProvider',
        'Mini\Mail\ConsoleServiceProvider',
        'Mini\Routing\ConsoleServiceProvider',

        // The Application Providers
        'App\Providers\AppServiceProvider',
        'App\Providers\EventServiceProvider',
        'App\Providers\RouteServiceProvider',
    ),

    'manifest' => storage_path(),

    /**
     * The registered Class Aliases.
     */
    'aliases' => array(
        'App'       => 'Mini\Support\Facades\App',
        'Auth'      => 'Mini\Support\Facades\Auth',
        'Cache'     => 'Mini\Support\Facades\Cache',
        'Config'    => 'Mini\Support\Facades\Config',
        'Cookie'    => 'Mini\Support\Facades\Cookie',
        'Crypt'     => 'Mini\Support\Facades\Crypt',
        'DB'        => 'Mini\Support\Facades\DB',
        'Event'     => 'Mini\Support\Facades\Event',
        'File'      => 'Mini\Support\Facades\File',
        'Forge'     => 'Mini\Support\Facades\Forge',
        'Hash'      => 'Mini\Support\Facades\Hash',
        'Input'     => 'Mini\Support\Facades\Input',
        'Lang'      => 'Mini\Support\Facades\Lang',
        'Log'       => 'Mini\Support\Facades\Log',
        'Mailer'    => 'Mini\Support\Facades\Mailer',
        'Redirect'  => 'Mini\Support\Facades\Redirect',
        'Response'  => 'Mini\Support\Facades\Response',
        'Route'     => 'Mini\Support\Facades\Route',
        'Schedule'  => 'Mini\Support\Facades\Schedule',
        'Session'   => 'Mini\Support\Facades\Session',
        'URL'       => 'Mini\Support\Facades\URL',
        'Validator' => 'Mini\Support\Facades\Validator',
        'View'      => 'Mini\Support\Facades\View',
    ),
);
