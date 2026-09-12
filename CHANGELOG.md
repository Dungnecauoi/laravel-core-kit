# Changelog

## Unreleased

## 0.1.0 — 2026-09-12

- `MenuRegistry` and `SettingsRegistry` — extension-point registries for sidebar items and settings tabs, keyed for safe re-registration (a repeated `key` replaces instead of duplicating).
- `MenuItemRegistered` and `SettingsPanelRegistered` events, fired on every `register()` call.
- `MenuRegistry::filter()` — pipeline-based transformation of the resolved menu (`Illuminate\Pipeline\Pipeline`), for cases like hiding an item by permission.
- `duxbo/laravel-ai-core` required directly, so any app or kit depending only on this package gets `Duxbo\AiCore\AiManager` through Laravel's package auto-discovery, with no separate install step.
- Octane-safety documented and tested: both registries are singletons, populated once at `boot()`, safe against duplicate accumulation if ever called from request-scoped code.
