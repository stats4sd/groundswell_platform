# Tailwind v3 → v4 cleanup: blade templates + theme.css bare-border rules

**Status:** Completed — see [change-log](../change-logs/tailwind-v4-template-class-cleanup.md).

## Context

The Filament 5 / Tailwind v4 upgrade (see [docs/change-logs/filament-5-tailwind-v4-theme-and-config.md](../change-logs/filament-5-tailwind-v4-theme-and-config.md)) migrated the build toolchain and the two CSS entrypoints (`theme.css`, `cover-page.css`) to Tailwind v4. That work was sound. What it did not cover was the *content* of the blade templates and a few `@apply` rules inside `theme.css`: several Tailwind v3 utility classes survived in the markup, compiling cleanly under v4 but rendering incorrectly (dropped opacity, wrong border colour, heavier shadow, lost flex behaviour). This plan fixed those leftover classes.

Out of scope (per user decision): the `packages/filament-odk-link` build mismatch (pins `tailwindcss ^3.3.3` while its CSS uses v4 `@source`).

## Changes

1. **`bg-opacity-*` → slash modifier** (highest priority, real break). `results-page.blade.php` `bg-black bg-opacity-50` → `bg-black/50`; `survey-dashboard.blade.php` 12× `bg-white bg-opacity-50` → `bg-white/50`.
2. **`flex-shrink-0` → `shrink-0`**: `red-alert-box.blade.php`, `initial-pilot.blade.php`, `pilot-index.blade.php`.
3. **Bare border → explicit `border-gray-200`** (v4 default changed gray-200 → currentColor): `theme.css` `.fi-ta-reorder-indicator`/`.fi-ta-ctn`/`.dropdown_tables .fi-ta-content`; blades `team-translation-review.blade.php`, `choice-list-entries-info.blade.php`. Left `offline-action-section`/`rounded-section` (already coloured via `.actionblockborder`).
4. **`shadow-sm` → `shadow-xs`** (cosmetic rescale): `cover-page.blade.php` (3 cards), `stat.blade.php` (published Filament widget view).
5. **`space-x/y`**: verify-only; no layout shift found, left untouched.

## Verification

`npm run build` green (only pre-existing Leaflet chunk-size warning); compiled `theme.css` confirmed to emit `.shrink-0`, `.shadow-xs`, `.border-gray-200`, `.bg-white/50`, `.bg-black/50`. Manual visual checks: results-page hero overlay translucent, survey-dashboard progress tracks translucent, alert icon columns keep fixed width, table/divider borders light grey.
