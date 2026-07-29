# Filament 5 upgrade — Tailwind v4 theme migration + config

Implements **steps 5 (Tailwind v4 theme migration) and 6 (config)** of [the Filament 3→5 / Livewire 3→4 / Laravel 11→13 upgrade plan](../archive/plans/filament-5-livewire-4-laravel-13-upgrade.md). Steps 1–4 (package removals, constraint bumps, `composer update`, the v4+v5 codemods, manual breaking changes) had already landed; this completes the frontend theme port and config merge so `npm run build` is green and the v5 config keys are present.

## Step 5 — Tailwind v3 → v4

Filament 5.6.7 ships its theme as Tailwind v4 (`@import 'tailwindcss' source(none)`), which Tailwind 3 cannot parse — so `npm run build` was hard-failing on the (now-removed) `tailwind.config.preset`. Migrated the toolchain and the single custom panel theme to Tailwind v4.

### Build toolchain
- [package.json](../../package.json): `tailwindcss` `^3.4.15` → `^4.1.0`; added `@tailwindcss/vite` `^4.1.0`; removed `autoprefixer`, `postcss`, `postcss-nesting` (v4's engine handles nesting + prefixing), and `@tailwindcss/forms` / `@tailwindcss/typography` (Filament v4/v5 styles forms natively; no `prose` usage anywhere in the app).
- [vite.config.js](../../vite.config.js): added the `@tailwindcss/vite` plugin.
- Deleted the v3 configs: root `tailwind.config.js`, `resources/css/filament/app/tailwind.config.js`, and `postcss.config.js`.

### Theme CSS — [resources/css/filament/app/theme.css](../../resources/css/filament/app/theme.css)
- Replaced the v3 header (`@import '/vendor/.../theme.css'` with an erroneous leading slash, plus `@config 'tailwind.config.js'`) with the v4 pattern: `@import '../../../../vendor/filament/filament/resources/css/theme.css'` (correct relative path) followed by `@source` directives. The Filament import uses `source(none)`, so the host app must declare its own template sources — added `@source` globs for `app/`, `resources/views`, `resources/js` (`.js`/`.vue`), `vendor/filament` blade, and both local `packages/*` (src + views), matching the old config's `content` coverage.
- Ported the custom palette + font from the deleted JS config into a v4 `@theme` block (`--color-brown/green/orange/blue/dark-orange/light-orange/lightgreen/light-grey/grey/hyellow` and `--font-montserrat`). The tokens map to the existing `:root` CSS variables (kept verbatim) so both the generated utilities (`bg-green`, `text-brown`, `font-montserrat`, …) and the many raw `var(--green)` references in custom CSS keep working from a single source.
- Hoisted the Google Fonts `@import url(...)` above the Filament theme import (CSS requires `@import` rules to precede all other rules; the theme import expands to real CSS, which otherwise triggered a Lightning CSS warning).
- v4 `@apply` no longer accepts arbitrary custom classes or a trailing `!important` keyword:
  - `.buttonb` → declared as `@utility buttonb` so it is still usable in markup **and** can be pulled in via `@apply` (used by `.fi-modal-slide-over-window .fi-btn`).
  - `@apply gap-y-0 !important` → `@apply gap-y-0!` (v4 trailing-bang important syntax).
- Leading-`!` important utilities (`!ring-0`, `!text-lg`, …) are still valid in v4 and were left unchanged.

### Cover page CSS — [resources/css/cover-page.css](../../resources/css/cover-page.css)
- Added `@reference 'tailwindcss'` so its `@apply` usages resolve without emitting a duplicate Tailwind base (this file only layers overrides on top of the separately-loaded Filament theme).
- `.category-card` `@apply`'d the custom `bg-yellowgreen`/`text-darkgreen` classes (not real utilities under v4) — moved those two to direct `background-color`/`color` declarations, keeping the built-in utilities in `@apply`.

### Build verification
`npm run build` is green (no warnings). Confirmed in the compiled `theme.css`: the `--color-*` variables resolve to the palette, the custom utilities (`bg-green`, `text-brown`, `border-green`, `bg-lightgreen`, …) are emitted, and `body` resolves `font-family: var(--font-montserrat)` → `"Montserrat", sans-serif`. The `public-map` chunk-size warning is pre-existing (Leaflet) and unrelated.

## Step 6 — Config

- [config/filament.php](../../config/filament.php): merged the three new v5 keys into the existing file rather than `--force`-publishing (which would have clobbered the app's custom Reverb `broadcasting.echo` block and the `public` filesystem-disk default): `temporary_file_url_expiry_minutes` (30), `file_generation.flags` (`[]`), and `system_route_prefix` (`filament`). Existing app-specific settings preserved.
- Ran `php artisan filament:upgrade` (the `post-autoload-dump` hook) — assets republished successfully.
- `filament:upgrade-directory-structure-to-v4` (the optional/cosmetic dir-restructure) does **not** exist in Filament 5.6.7 (it was a v4-era command, removed in v5) — nothing to defer.

## Test impact

Frontend + config changes do not touch PHP behaviour. Test suite before and after steps 5–6 is identical: **14 failed / 71 passed**. The 14 failures are the step-4-class app regressions called out in the plan's Validation phase (Admin CRUD `ComponentNotFoundException`, App CRUD `ActionNotResolvableException`, HddsHints `ViewException`, Admin/App smoke 500s, Program-panel `programs` 404) and are the next piece of work.
