<?php

namespace System\Http\Middleware;

use Closure;


class FrameGuard
{
    /**
     * Handle the given request and get the response.
     *
     * @param  \System\Http\Request  $request
     * @param  \Closure  $next
     * @return \System\Http\Response
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);

        return $response;
    }
}
