# Laravel Core

Framework-agnostic core for the duxbo starter-kit line. This package owns every piece of **stateful, extensible logic** that a UI kit (Blade, and later Vue/React) only renders — it never renders anything itself.

Think WordPress core vs. a theme: this package is core, `laravel-blade-kit` (and future `laravel-vue-kit`/`laravel-react-kit`) are themes. Swapping the kit changes how the admin looks; it never changes what menu items or settings tabs exist — those live here, once, regardless of which kit is installed on top.

AI itself is not implemented here — it's a real `require` on [`duxbo/laravel-ai-core`](https://github.com/Dungnecauoi/laravel-ai-core), which already ships a driver-based `AiManager` (Claude/OpenAI/Gemini/Groq/OpenRouter), budget tracking, a circuit breaker, and its own Octane support. Because it's a dependency of this package rather than a sibling you opt into separately, **anything that requires `duxbo/laravel-core` gets `Duxbo\AiCore\AiManager` for free** — Laravel's package auto-discovery picks it up transitively, no extra `composer require` and no wiring in `CoreServiceProvider`. `laravel-core` doesn't wrap or re-expose its API; you use `app(\Duxbo\AiCore\AiManager::class)` (or its facade) exactly as that package's own README documents.

## What's included

- **`MenuRegistry`** — lets the host app or any package register sidebar menu items (optionally nested under a parent) without a kit needing to know who registered what.
- **`SettingsRegistry`** — lets the host app or any package register a tab/panel on the settings page.
- **Events** — `MenuItemRegistered`, `SettingsPanelRegistered` fire whenever something is registered, so a package can react without polling the registries.
- **Pipeline filters** — `MenuRegistry::filter()` lets a package transform the resolved menu (e.g. hide an item by permission) without wrapping or replacing anything.
- **`starter-kit:install`** — the one command a new project runs: pick a frontend kit and optional feature packages, and it wires `composer.json`, runs `composer require`, delegates to the kit's own install command, and records the choice.

## Installation

```bash
composer require duxbo/laravel-core
```

## `starter-kit:install`

```bash
php artisan starter-kit:install
```

Asks two things:

1. **Frontend kit** — currently only Blade (`duxbo/laravel-blade-kit`). A future Vue/React entry in `StarterKitInstallCommand::KITS` would additionally ask for a UI library and Inertia-vs-API; Blade doesn't have that choice, so neither question appears.
2. **Optional feature packages** (multi-select) — `duxbo/laravel-auth`, `duxbo/laravel-seo`. `duxbo/laravel-ai-core` is never offered here: it's already a dependency of this package, so it's installed the moment `duxbo/laravel-core` is.

For each answer it adds the matching `vcs` repository to the host app's `composer.json` if missing, runs `composer require` for it, and — for the chosen kit only — runs that kit's own `{kit}:install` command as a fresh process (so it sees the package composer just required). The resulting stack is written to `config/starter-kit.php`:

```php
return [
    'frontend' => 'blade',
    'ui_library' => null,
    'mode' => null,
    'features' => ['seo'],
];
```

Re-running the command once `config/starter-kit.php` exists refuses by default — pass `--force` to redo it.

This command only orchestrates `composer require` + delegation; it never contains a kit's own install logic (copying view stubs, publishing config, and so on), which stays entirely inside that kit's package.

The service provider is auto-discovered.

## Registering menu items and settings tabs

Call from any service provider's `boot()` — yours or a package's:

```php
use LaravelCore\Menu\MenuRegistry;
use LaravelCore\Settings\SettingsRegistry;

// Top-level sidebar item:
app(MenuRegistry::class)->register([
    'key' => 'media',
    'label' => 'Media',
    'icon' => 'image',
    'route' => 'media.index',
]);

// Nested under an existing item:
app(MenuRegistry::class)->register($item, parentKey: 'management');

// A settings tab — the view is included as-is, self-contained:
app(SettingsRegistry::class)->register('media', [
    'label' => 'Media',
    'icon' => 'image',
    'view' => 'laravel-media::settings-panel',
    'order' => 20,
]);
```

A UI kit reads these back to render the sidebar / settings page (`$menuRegistry->items()`, `$menuRegistry->childrenFor($key)`, `$settingsRegistry->all()`) — it never decides what's in them.

**Only call `register()` from `boot()`.** See "Laravel Octane compatibility" below for why.

## Reacting to events, and filtering values in flight

Two different needs, two different mechanisms — deliberately not one generic WordPress-style hook bus, so everything stays type-checked:

**"Something happened, let me react"** — listen for the event, same as any other Laravel event:

```php
Event::listen(function (MenuItemRegistered $event) {
    Log::info('menu item registered', ['label' => $event->item['label']]);
});
```

Also fired: `SettingsPanelRegistered`.

**"Let me transform this value before it's used"** — register a pipeline filter. A filter is a closure `fn ($value, Closure $next) => $next($value)`, or a class implementing `MenuFilter` with a `handle()` method of the same shape:

```php
// Hide sidebar items the current user can't access:
app(MenuRegistry::class)->filter(function (array $items, Closure $next) {
    return $next(array_values(array_filter($items, fn ($item) => Gate::allows('view', $item))));
});
```

## Laravel Octane compatibility

Under Octane, one application instance is kept alive for the life of a worker and reused across many requests — a service provider's `boot()` runs **once**, when the worker starts, not per request. `MenuRegistry` and `SettingsRegistry` are both bound as singletons on purpose, so:

- Registering once at `boot()` populates the registry for the entire life of the worker — the same guarantee PHP-FPM gives you per request, just amortized across many requests instead of paid again each time.
- Registering from **request-scoped code** (a controller, middleware, a route closure) would instead keep appending for as long as the worker stays alive, since the singleton is never recreated between requests. Don't do that — register from `boot()` only.
- As a safety net against that mistake, both registries are keyed: an item/panel registered with the same `key` twice replaces the previous entry instead of piling up a duplicate on every call.

`tests/OctaneSafetyTest.php` asserts these singleton guarantees directly, and `MenuRegistryTest`/`SettingsRegistryTest` assert the key-based upsert behavior that keeps repeated registration safe.

## Testing

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint          # code style
```

CI (`.github/workflows/tests.yml`) runs the suite on PHP 8.2/8.3 against Laravel 11/12/13.

## Releasing

Consumers on a `path` repository read this package's version from its own `composer.json` `"version"` field, not from a git tag — a release is three edits kept in sync in one commit, same as `laravel-ai-core`:

1. Bump `"version"` in `composer.json`.
2. Move the current `## Unreleased` entry in `CHANGELOG.md` to a dated `## x.y.z — YYYY-MM-DD` heading, and update `extra.branch-alias`'s `dev-main` to the next unreleased version.
3. Tag and push: `git tag -a vX.Y.Z -m "X.Y.Z - <summary>" && git push origin main && git push origin vX.Y.Z`.

## Relationship to `laravel-blade-kit`

`laravel-blade-kit` currently ships its own copies of `MenuRegistry`/`SettingsRegistry` as stub files copied into the host app. Once this package is in place, blade-kit's installer should `composer require duxbo/laravel-core` instead of copying those two files — the Blade `sidebar-content.blade.php` / settings view only need to read from the registries, not own them. That migration is a follow-up, not done by this package.

## License

MIT.
