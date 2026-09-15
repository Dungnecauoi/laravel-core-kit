<?php

namespace LaravelCore\Tests;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LaravelCore\CoreServiceProvider;

/**
 * Regression test for the exact race the 'admin.auth' guard in
 * CoreServiceProvider::boot() exists to prevent: composer's discovery
 * order boots packages roughly alphabetically, so a real auth package
 * ("duxbo/laravel-auth") boots *before* "duxbo/laravel-core" in practice.
 * AdminAuthMiddlewareTest only proves an override made *after* core has
 * already booted wins — it never actually simulates a provider that
 * claims the alias *first*. If the `! array_key_exists(...)` guard were
 * ever "cleaned up" as apparently redundant, nothing else here would
 * catch it — this test exists so that regression is caught automatically
 * instead of relying on remembering to re-verify by hand in a scratch app.
 */
class AdminAuthBootOrderTest extends TestCase
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        // Registered (and therefore booted) before CoreServiceProvider,
        // mirroring duxbo/laravel-auth winning the alphabetical race
        // against duxbo/laravel-core in a real app's vendor/composer.json.
        return [EarlyAdminAuthProvider::class, CoreServiceProvider::class];
    }

    public function test_a_package_that_boots_before_core_keeps_its_own_admin_auth_override(): void
    {
        Route::get('/kit-admin-route-early-auth', fn () => 'ok')->middleware('admin.auth');

        $this->get('/kit-admin-route-early-auth')->assertForbidden();
    }
}

class EarlyAdminAuthProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('admin.auth', DenyEverythingBeforeCoreBoots::class);
    }
}

class DenyEverythingBeforeCoreBoots
{
    public function handle(Request $request, Closure $next)
    {
        abort(403);
    }
}
