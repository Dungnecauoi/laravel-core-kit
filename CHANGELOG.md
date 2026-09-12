# Changelog

## Unreleased

## 0.3.0 — 2026-09-12

- `MenuRegistry::items()`/`childrenFor()` and `SettingsRegistry::all()` now translate `label` through `__()` before returning it — one place, so every kit gets translated labels automatically instead of each needing to remember to call `__()` itself.
- New `admin.auth` middleware alias, registered as a pass-through (`LaravelCore\Http\Middleware\AllowAll`) unless something else has already claimed it. Every kit's admin routes carry `->middleware('admin.auth')`; a real auth package overrides the same alias to turn on login enforcement, without either side depending on the other. Registration is order-independent by design — verified end-to-end with `duxbo/laravel-blade-kit` + `duxbo/laravel-auth` in a clean scratch app (302 → `/login` once auth is installed; pass-through before that).

## 0.2.2 — 2026-09-12

- `starter-kit:install`: add `react` to the frontend choice (`duxbo/laravel-react-kit`). Kits can now declare `needs_ui_library`/`needs_mode` in `self::KITS` to turn on the extra prompts (UI library, rendering mode) and have them passed as `--ui=`/`--mode=` to the kit's own install command — Blade still skips both since it declares neither flag.

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
