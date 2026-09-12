<?php

namespace LaravelCore;

use Illuminate\Support\ServiceProvider;
use LaravelCore\Console\StarterKitInstallCommand;
use LaravelCore\Menu\MenuRegistry;
use LaravelCore\Settings\SettingsRegistry;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MenuRegistry::class);
        $this->app->singleton(SettingsRegistry::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                StarterKitInstallCommand::class,
            ]);
        }
    }
}
