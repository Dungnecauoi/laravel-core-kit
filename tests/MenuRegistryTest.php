<?php

namespace LaravelCore\Tests;

use Closure;
use Illuminate\Support\Facades\Event;
use LaravelCore\Events\MenuItemRegistered;
use LaravelCore\Menu\MenuRegistry;

class MenuRegistryTest extends TestCase
{
    public function test_it_registers_top_level_items(): void
    {
        $registry = new MenuRegistry;

        $registry->register(['label' => 'Dashboard']);
        $registry->register(['label' => 'Media']);

        $this->assertSame(
            ['Dashboard', 'Media'],
            array_column($registry->items(), 'label'),
        );
    }

    public function test_it_nests_items_under_a_parent_key(): void
    {
        $registry = new MenuRegistry;

        $registry->register(['label' => 'Users'], parentKey: 'management');
        $registry->register(['label' => 'Roles'], parentKey: 'management');

        $this->assertSame(
            ['Users', 'Roles'],
            array_column($registry->childrenFor('management'), 'label'),
        );
        $this->assertSame([], $registry->childrenFor('unknown'));
    }

    public function test_re_registering_the_same_key_replaces_instead_of_duplicating(): void
    {
        $registry = new MenuRegistry;

        $registry->register(['key' => 'media', 'label' => 'Media']);
        $registry->register(['key' => 'media', 'label' => 'Media (renamed)']);

        $this->assertCount(1, $registry->items());
        $this->assertSame('Media (renamed)', $registry->items()[0]['label']);
    }

    public function test_items_without_a_key_never_collapse_into_each_other(): void
    {
        // Guards the exact Octane risk this class exists to avoid: if
        // register() is ever called many times on a long-lived worker,
        // items without an explicit key must keep accumulating distinctly
        // rather than silently overwriting each other.
        $registry = new MenuRegistry;

        for ($i = 0; $i < 50; $i++) {
            $registry->register(['label' => "Item {$i}"]);
        }

        $this->assertCount(50, $registry->items());
    }

    public function test_registering_an_item_dispatches_an_event(): void
    {
        Event::fake();

        $registry = new MenuRegistry;
        $registry->register(['key' => 'media', 'label' => 'Media'], parentKey: 'management');

        Event::assertDispatched(
            MenuItemRegistered::class,
            fn (MenuItemRegistered $event) => $event->item['label'] === 'Media' && $event->parentKey === 'management',
        );
    }

    public function test_filters_can_transform_items_before_they_are_read(): void
    {
        $registry = new MenuRegistry;
        $registry->register(['key' => 'media', 'label' => 'Media']);
        $registry->register(['key' => 'billing', 'label' => 'Billing']);

        $registry->filter(function (array $items, Closure $next) {
            return $next(array_values(array_filter($items, fn ($item) => $item['key'] !== 'billing')));
        });

        $this->assertSame(['media'], array_column($registry->items(), 'key'));
    }

    public function test_filters_also_apply_to_children(): void
    {
        $registry = new MenuRegistry;
        $registry->register(['key' => 'users', 'label' => 'Users'], parentKey: 'management');

        $registry->filter(function (array $items, Closure $next) {
            return $next(array_map(function (array $item) {
                $item['label'] = strtoupper($item['label']);

                return $item;
            }, $items));
        });

        $this->assertSame('USERS', $registry->childrenFor('management')[0]['label']);
    }
}
