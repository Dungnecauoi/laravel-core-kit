# Changelog

## Unreleased

## 0.2.1 — 2026-09-12

- Fix `starter-kit:install`: also add the `duxbo/laravel-core` and `duxbo/laravel-ai-core` `vcs` repositories before requiring a kit. Repositories declared inside a dependency's own `composer.json` are not inherited by the app requiring it — composer only reads the root project's `repositories` — so without this, `composer require duxbo/laravel-blade-kit` failed to resolve in a clean app. Found by actually requiring blade-kit into a scratch app end-to-end, not just by reading the code.

## 0.2.0 — 2026-09-12

- `starter-kit:install` — interactive installer: picks a frontend kit (currently Blade), optional feature packages (`laravel-auth`, `laravel-seo`, `laravel-media`), wires host `composer.json` repositories, runs `composer require`, delegates to the kit's own install command, and records the choice in `config/starter-kit.php`.

## 0.1.0 — 2026-09-12

- `MenuRegistry` and `SettingsRegistry` — extension-point registries for sidebar items and settings tabs, keyed for safe re-registration (a repeated `key` replaces instead of duplicating).
- `MenuItemRegistered` and `SettingsPanelRegistered` events, fired on every `register()` call.
- `MenuRegistry::filter()` — pipeline-based transformation of the resolved menu (`Illuminate\Pipeline\Pipeline`), for cases like hiding an item by permission.
- `duxbo/laravel-ai-core` required directly, so any app or kit depending only on this package gets `Duxbo\AiCore\AiManager` through Laravel's package auto-discovery, with no separate install step.
- Octane-safety documented and tested: both registries are singletons, populated once at `boot()`, safe against duplicate accumulation if ever called from request-scoped code.
