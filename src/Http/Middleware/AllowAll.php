<?php

namespace LaravelCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Default binding for the 'admin.auth' middleware alias every kit's admin
 * routes go through. Passes every request through unchanged — there is
 * nothing to protect until a real auth package (duxbo/laravel-auth) is
 * installed and overrides this alias with its own middleware. Neither
 * side needs to know the other exists: a kit's routes always carry
 * ->middleware('admin.auth'), and whichever class the alias currently
 * points to decides what that means.
 */
class AllowAll
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
