<?php

namespace LaravelCore\Tests;

use Illuminate\Support\Facades\Event;
use LaravelCore\Events\SettingsPanelRegistered;
use LaravelCore\Settings\SettingsRegistry;

class SettingsRegistryTest extends TestCase
{
    public function test_it_sorts_panels_by_order(): void
    {
        $registry = new SettingsRegistry;

        $registry->register('appearance', ['label' => 'Appearance', 'view' => 'x', 'order' => 30]);
        $registry->register('profile', ['label' => 'Profile', 'view' => 'x', 'order' => 10]);
        $registry->register('permissions', ['label' => 'Permissions', 'view' => 'x', 'order' => 20]);

        $this->assertSame(
            ['profile', 'permissions', 'appearance'],
            array_column($registry->all(), 'key'),
        );
    }

    public function test_default_order_is_100_when_not_given(): void
    {
        $registry = new SettingsRegistry;

        $registry->register('media', ['label' => 'Media', 'view' => 'x']);

        $this->assertSame(100, $registry->all()[0]['order']);
    }

    public function test_re_registering_the_same_key_replaces_it(): void
    {
        $registry = new SettingsRegistry;

        $registry->register('media', ['label' => 'Media', 'view' => 'x']);
        $registry->register('media', ['label' => 'Media (v2)', 'view' => 'y']);

        $this->assertCount(1, $registry->all());
        $this->assertSame('Media (v2)', $registry->all()[0]['label']);
    }

    public function test_registering_a_panel_dispatches_an_event(): void
    {
        Event::fake();

        $registry = new SettingsRegistry;
        $registry->register('media', ['label' => 'Media', 'view' => 'x']);

        Event::assertDispatched(
            SettingsPanelRegistered::class,
            fn (SettingsPanelRegistered $event) => $event->key === 'media',
        );
    }
}
