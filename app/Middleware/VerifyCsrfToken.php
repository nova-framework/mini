<?php

namespace App\Middleware;

use Mini\Http\Request;
use Mini\Session\Store as SessionStore;
use Mini\Session\TokenMismatchException;

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
