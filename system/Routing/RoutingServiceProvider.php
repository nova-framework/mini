<?php

namespace System\Routing;

use System\Support\ServiceProvider;


class RoutingServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerRouter();

        $this->registerUrlGenerator();

        $this->registerRedirector();
    }

    /**
     * Register the router instance.
     *
     * @return void
     */
    protected function registerRouter()
    {
        $this->app->singleton('router', function ($app)
        {
            $config = $app['config'];

            return new Router(
                $app,
                $config->get('app.routeMiddleware', array()),
                $config->get('app.middlewareGroups', array())
            );
        });
    }

    /**
     * Register the URL generator service.
     *
     * @return void
     */
    protected function registerUrlGenerator()
    {
        $this->app->singleton('url', function ($app)
        {
            $router = $app['router'];

            $url = new UrlGenerator($router->getRoutes(), $app->rebinding('request', function ($app, $request)
            {
                $app['url']->setRequest($request);
            }));

            $url->setSessionResolver(function ()
            {
                return $this->app['session'];
            });

            return $url;
        });
    }

    /**
     * Register the Redirector service.
     *
     * @return void
     */
    protected function registerRedirector()
    {
        $this->app->singleton('redirect', function ($app)
        {
            $redirector = new Redirector($app['url']);

            if (isset($app['session.store'])) {
                $redirector->setSession($app['session.store']);
            }

            return $redirector;
        });
    }
}
