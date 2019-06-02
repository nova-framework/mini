<?php

namespace Mini\Cookie\Middleware;

use Mini\Foundation\Application;

use Closure;


class AddQueuedCookiesToResponse
{
    /**
     * The cookie jar instance.
     *
     * @var \Nova\Cookie\CookieJar
     */
    protected $cookies;

    /**
     * Create a new CookieQueue instance.
     *
     * @param  \Nova\Foundation\Application  $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->cookies = $app['cookie'];
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Nova\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        //
        $cookies = $this->cookies->getQueuedCookies();

        foreach ($cookies as $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
