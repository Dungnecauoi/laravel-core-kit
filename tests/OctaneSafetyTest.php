<?php

namespace LaravelCore\Tests;

use LaravelCore\Menu\MenuRegistry;
use LaravelCore\Settings\SettingsRegistry;

/**
 * Laravel Octane keeps one application instance alive for the life of a
 * worker and reuses it across many requests. Anything registered here must
 * therefore be bound as a singleton — a fresh instance per resolution would
 * mean a menu item registered by one package's boot() never reaches the
 * sidebar rendered by a later, unrelated request on the same worker.
 */
class OctaneSafetyTest extends TestCase
{
    public function test_menu_registry_is_a_singleton(): void
    {
        $this->assertSame(
            $this->app->make(MenuRegistry::class),
            $this->app->make(MenuRegistry::class),
        );
    }

    public function test_settings_registry_is_a_singleton(): void
    {
        $this->assertSame(
            $this->app->make(SettingsRegistry::class),
            $this->app->make(SettingsRegistry::class),
        );
    }

    public function test_a_registration_made_once_survives_across_simulated_requests(): void
    {
        // Mirrors boot() running exactly once when an Octane worker starts:
        // register a single time, then read it back as if from several
        // later, unrelated requests handled by the same long-lived worker.
        $registry = $this->app->make(MenuRegistry::class);
        $registry->register(['key' => 'media', 'label' => 'Media']);

        for ($simulatedRequest = 0; $simulatedRequest < 3; $simulatedRequest++) {
            $this->assertCount(1, $this->app->make(MenuRegistry::class)->items());
        }
    }
}
