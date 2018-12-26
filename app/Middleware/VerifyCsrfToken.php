<?php

namespace App\Middleware;

use System\Http\Request;
use System\Session\Store as SessionStore;
use System\Session\TokenMismatchException;

use Closure;


class VerifyCsrfToken
{

    public function handle(Request $request, Closure $next)
    {
        $session = $request->session();

        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        if ($session->token() !== $token) {
            throw new TokenMismatchException();
        }

        return $next($request);
    }
}
