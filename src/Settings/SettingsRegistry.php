<?php

namespace LaravelCore\Settings;

use Illuminate\Support\Collection;
use LaravelCore\Events\SettingsPanelRegistered;

/**
 * Collects settings tabs/panels registered by the host app or any package's
 * service provider — call only from a provider's boot(), never from
 * request-scoped code, for the same Octane reasons documented on
 * MenuRegistry.
 *
 *   app(SettingsRegistry::class)->register('media', [
 *       'label' => 'Media',
 *       'icon' => 'image',
 *       'view' => 'laravel-media::settings-panel',
 *       'order' => 20,
 *   ]);
 *
 * The view is included as-is (no data passed in) — it's expected to be a
 * self-contained view that computes whatever it needs. Panels are keyed
 * by $key, so re-registering the same key replaces it instead of adding a
 * duplicate — the same worker-lifetime guarantee MenuRegistry relies on.
 *
 * register() dispatches SettingsPanelRegistered, so any package can react
 * to what's being added without polling all() itself.
 */
class SettingsRegistry
{
    /** @var array<string, array> */
    private array $panels = [];

    public function register(string $key, array $panel): static
    {
        $this->panels[$key] = array_merge(['key' => $key, 'order' => 100], $panel);

        SettingsPanelRegistered::dispatch($key, $this->panels[$key]);

        return $this;
    }

    /** @return list<array{key: string, label: string, icon: ?string, view: string, order: int}> */
    public function all(): array
    {
        return Collection::make($this->panels)
            ->sortBy('order')
            ->values()
            ->all();
    }
}
