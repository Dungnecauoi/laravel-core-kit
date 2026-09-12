<?php

namespace LaravelCore\Tests;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class AdminAuthMiddlewareTest extends TestCase
{
    public function test_admin_auth_defaults_to_a_pass_through_middleware(): void
    {
        Route::get('/kit-admin-route', fn () => 'ok')->middleware('admin.auth');

        $this->get('/kit-admin-route')->assertOk()->assertSee('ok');
    }

    public function test_a_real_auth_package_can_override_the_admin_auth_alias(): void
    {
        // Stands in for what duxbo/laravel-auth's own service provider does
        // on boot: override the exact same alias with real middleware,
        // without laravel-core or the kit knowing it exists.
        $this->app['router']->aliasMiddleware('admin.auth', DenyEverything::class);

        Route::get('/kit-admin-route-protected', fn () => 'ok')->middleware('admin.auth');

        $this->get('/kit-admin-route-protected')->assertForbidden();
    }
}

class DenyEverything
{
    public function handle(Request $request, Closure $next)
    {
        abort(403);
    }
}
