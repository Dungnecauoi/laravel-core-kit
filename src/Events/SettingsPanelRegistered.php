<?php

namespace LaravelCore\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired every time SettingsRegistry::register() is called.
 */
class SettingsPanelRegistered
{
    use Dispatchable;

    public function __construct(
        public readonly string $key,
        public readonly array $panel,
    ) {}
}
