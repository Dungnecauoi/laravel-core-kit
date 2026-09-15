<?php

namespace LaravelCore\Menu;

use Closure;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use LaravelCore\Events\MenuItemRegistered;
use LaravelCore\Menu\Contracts\MenuFilter;

/**
 * Collects sidebar menu items registered by the host app or any package's
 * service provider — call only from a provider's boot(), never from
 * request-scoped code (controllers, middleware, route closures).
 *
 *   app(MenuRegistry::class)->register([
 *       'key' => 'media',
 *       'label' => 'Media',
 *       'icon' => 'image',
 *       'route' => 'media.index',
 *   ]);
 *
 * Pass a $parentKey to nest under an existing item instead:
 *
 *   app(MenuRegistry::class)->register($item, parentKey: 'management');
 *
 * Items are sorted by an 'order' key (default 100, same convention as
 * SettingsRegistry) before being returned — without this, position in the
 * sidebar would depend on which package's service provider happened to
 * boot first (composer's discovery order), the exact class of bug that
 * motivated the 'admin.auth' alias's own boot-order guard.
 *
 * Register a filter to transform the resolved list before a kit renders it
 * (e.g. hide items the current user can't access):
 *
 *   app(MenuRegistry::class)->filter(function (array $items, Closure $next) {
 *       return $next(array_values(array_filter($items, fn ($i) => Gate::allows('view', $i))));
 *   });
 *
 * register() also dispatches MenuItemRegistered, so any package can react
 * to what's being added without polling items() itself.
 *
 * This class is bound as a singleton. Under Laravel Octane a singleton
 * lives for the entire life of a worker, and boot() runs once when that
 * worker starts — so registering here is a one-time, worker-wide operation,
 * the same guarantee PHP-FPM gives you per request. Registering from
 * request-scoped code instead would keep appending for as long as the
 * worker stays alive, which is why items (and children) that carry a 'key'
 * are upserted by that key rather than appended — re-registering the same
 * key replaces it instead of duplicating it.
 */
class MenuRegistry
{
    /** @var array<int|string, array> */
    private array $items = [];

    /** @var array<string, array<int|string, array>> */
    private array $children = [];

    /** @var list<Closure|class-string<MenuFilter>> */
    private array $filters = [];

    private int $sequence = 0;

    public function register(array $item, ?string $parentKey = null): static
    {
        $key = $item['key'] ?? '__auto_'.$this->sequence++;
        $item = array_merge(['order' => 100], $item);

        if ($parentKey === null) {
            $this->items[$key] = $item;
        } else {
            $this->children[$parentKey][$key] = $item;
        }

        MenuItemRegistered::dispatch($item, $parentKey);

        return $this;
    }

    /** @param  Closure|class-string<MenuFilter>  $filter */
    public function filter(Closure|string $filter): static
    {
        $this->filters[] = $filter;

        return $this;
    }

    /** @return list<array> */
    public function items(): array
    {
        return $this->runThroughFilters($this->sortAndTranslate($this->items));
    }

    /** @return list<array> */
    public function childrenFor(string $parentKey): array
    {
        return $this->runThroughFilters($this->sortAndTranslate($this->children[$parentKey] ?? []));
    }

    /**
     * @param  array<int|string, array>  $items
     * @return list<array>
     */
    private function sortAndTranslate(array $items): array
    {
        return Collection::make($items)
            ->sortBy('order')
            ->values()
            ->map($this->translate(...))
            ->all();
    }

    /**
     * Translated here, once, so every kit (Blade, React, ...) gets a
     * translated label automatically — a kit that forgets to call __()
     * itself would otherwise silently ship untranslated strings.
     */
    private function translate(array $item): array
    {
        if (isset($item['label'])) {
            $item['label'] = __($item['label']);
        }

        return $item;
    }

    /**
     * @param  list<array>  $items
     * @return list<array>
     */
    private function runThroughFilters(array $items): array
    {
        if ($this->filters === []) {
            return $items;
        }

        return app(Pipeline::class)
            ->send($items)
            ->through($this->filters)
            ->thenReturn();
    }
}
