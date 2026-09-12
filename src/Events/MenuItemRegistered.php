<?php

namespace LaravelCore\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired every time MenuRegistry::register() is called — lets a package
 * observe what other packages are adding to the sidebar (logging,
 * auditing, building an unrelated feature on the same data) without
 * polling MenuRegistry::items() itself.
 */
class MenuItemRegistered
{
    use Dispatchable;

    public function __construct(
        public readonly array $item,
        public readonly ?string $parentKey,
    ) {}
}
