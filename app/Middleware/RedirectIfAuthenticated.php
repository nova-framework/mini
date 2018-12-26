<?php

namespace App\Middleware;

use System\Container\Container;
use System\Http\Request;
use System\Support\Facades\Auth;
use System\Support\Facades\Config;
use System\Support\Facades\Redirect;
use System\Support\Facades\Response;

use Closure;


class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \System\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $guard = null)
    {
        if (is_null($guard)) {
            $guard = Config::get('auth.default', 'web');
        }

        if (Auth::guard($guard)->guest()) {
            return $next($request);
        }

        // The User is authenticated.
        else if ($request->ajax() || $request->wantsJson() || $request->is('api/*')) {
            return Response::make('Unauthorized Access', 401);
        }

        $uri = Config::get("auth.guards.{$guard}.paths.dashboard", 'admin/dashboard');

        return Redirect::to($uri);
    }
}
