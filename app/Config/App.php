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
            'System\Cookie\Middleware\AddQueuedCookiesToResponse',
            'System\Session\Middleware\StartSession',
            'System\View\Middleware\ShareErrorsFromSession',
        ),
        'api' => array(
            'throttle:60,1',
        ),
    ),

    /**
     * The Platform's route Middleware.
     */
    'routeMiddleware' => array(
        'auth'     => 'System\Auth\Middleware\Authenticate',
        'guest'    => 'App\Middleware\RedirectIfAuthenticated',
        'throttle' => 'System\Routing\Middleware\ThrottleRequests',
        'csrf'     => 'App\Middleware\VerifyCsrfToken',
    ),

    /**
     * The registered Service Providers.
     */
    'providers' => array(
        'System\Auth\AuthServiceProvider',
        'System\Database\DatabaseServiceProvider',
        'System\Routing\RoutingServiceProvider',
        'System\Cookie\CookieServiceProvider',
        'System\Session\SessionServiceProvider',
        'System\Encryption\EncryptionServiceProvider',
        'System\Filesystem\FilesystemServiceProvider',
        'System\Hashing\HashServiceProvider',
        'System\Cache\CacheServiceProvider',
        'System\Mail\MailServiceProvider',
        'System\Pagination\PaginationServiceProvider',
        'System\Translation\TranslationServiceProvider',
        'System\Validation\ValidationServiceProvider',
        'System\View\ViewServiceProvider',

        // The Forge Providers.
        'System\Cache\ConsoleServiceProvider',
        'System\Foundation\Console\ConsoleSupportServiceProvider',
        'System\Mail\ConsoleServiceProvider',

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
        'App'       => 'System\Support\Facades\App',
        'Auth'      => 'System\Support\Facades\Auth',
        'Cache'     => 'System\Support\Facades\Cache',
        'Config'    => 'System\Support\Facades\Config',
        'Cookie'    => 'System\Support\Facades\Cookie',
        'Crypt'     => 'System\Support\Facades\Crypt',
        'DB'        => 'System\Support\Facades\DB',
        'Event'     => 'System\Support\Facades\Event',
        'File'      => 'System\Support\Facades\File',
        'Forge'     => 'System\Support\Facades\Forge',
        'Hash'      => 'System\Support\Facades\Hash',
        'Input'     => 'System\Support\Facades\Input',
        'Lang'      => 'System\Support\Facades\Lang',
        'Log'       => 'System\Support\Facades\Log',
        'Mailer'    => 'System\Support\Facades\Mailer',
        'Redirect'  => 'System\Support\Facades\Redirect',
        'Response'  => 'System\Support\Facades\Response',
        'Route'     => 'System\Support\Facades\Route',
        'Schedule'  => 'System\Support\Facades\Schedule',
        'Session'   => 'System\Support\Facades\Session',
        'URL'       => 'System\Support\Facades\URL',
        'Validator' => 'System\Support\Facades\Validator',
        'View'      => 'System\Support\Facades\View',
    ),
);
