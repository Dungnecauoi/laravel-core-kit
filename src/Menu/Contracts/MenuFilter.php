<?php

namespace LaravelCore\Menu\Contracts;

use Closure;

/**
 * A pipeline stage that can transform the resolved list of menu items
 * before a kit renders them — e.g. hiding items the current user lacks
 * permission for. Register with MenuRegistry::filter().
 */
interface MenuFilter
{
    /**
     * @param  list<array>  $items
     * @param  Closure(list<array>): list<array>  $next
     * @return list<array>
     */
    public function handle(array $items, Closure $next): array;
}
