<?php

use Mini\Http\Request;


Route::middleware('test', function (Request $request, Closure $next, $value)
{
    dump($value);

    return $next($request);
});
