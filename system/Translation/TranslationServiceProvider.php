<?php

namespace Mini\Translation;

use Mini\Support\ServiceProvider;


class TranslationServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;


    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('translator', function($app)
        {
            $config = $app['config'];

            //
            $locale = $config->get('app.locale', 'en');

            $translator = new Translator($app['files'], $app['path'] .DS .'Language', $locale);

            $translator->setFallback(
                $config->get('app.fallbackLocale', 'en')
            );

            return $translator;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('translator');
    }
}
