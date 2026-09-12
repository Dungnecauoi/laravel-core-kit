<?php

namespace LaravelCore;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use LaravelCore\Console\StarterKitInstallCommand;
use LaravelCore\Http\Middleware\AllowAll;
use LaravelCore\Menu\MenuRegistry;
use LaravelCore\Settings\SettingsRegistry;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MenuRegistry::class);
        $this->app->singleton(SettingsRegistry::class);
    }

    public function boot(Router $router): void
    {
        /**
         * Every kit's admin routes carry ->middleware('admin.auth'). This
         * default is a pass-through — installing a real auth package
         * (duxbo/laravel-auth) overrides this exact alias with its own
         * middleware, which is what actually turns login enforcement on.
         * Neither the kit nor the auth package needs to know about the
         * other; 'admin.auth' is the only thing they agree on.
         *
         * Registered only if nothing has claimed the alias yet, on
         * purpose: Laravel boots providers in an order this package does
         * not control (composer's discovery order, often alphabetical —
         * "duxbo/laravel-auth" boots before "duxbo/laravel-core"), so an
         * unconditional overwrite here would silently undo a real auth
         * package's override if that package happened to boot first. The
         * auth package's own override stays unconditional — it is always
         * meant to win regardless of boot order.
         */
        if (! array_key_exists('admin.auth', $router->getMiddleware())) {
            $router->aliasMiddleware('admin.auth', AllowAll::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                StarterKitInstallCommand::class,
            ]);
        }
    }
}
