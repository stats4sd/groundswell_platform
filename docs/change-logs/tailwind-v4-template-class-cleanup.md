# Tailwind v4 — leftover v3 utility classes in templates + theme.css

Implements [the Tailwind v3→v4 blade/theme cleanup plan](../plans/tailwind-v4-template-class-cleanup.md). The earlier [Filament 5 / Tailwind v4 theme + config migration](./filament-5-tailwind-v4-theme-and-config.md) ported the build toolchain and the two CSS entrypoints, but did not sweep the *content* of the blade templates. Several v3 utility classes survived in the markup and a few `@apply` rules in `theme.css`. These compiled without error under v4 but rendered incorrectly (dropped opacity, wrong border colour, heavier shadow, lost flex behaviour). This change fixes them so rendered output matches the pre-upgrade intent.

## Changes

### `bg-opacity-*` → slash modifier (functional break)
v4 does not generate the `bg-opacity-N` class, so the background rendered fully opaque.
- [resources/views/livewire/results-page.blade.php](../../resources/views/livewire/results-page.blade.php): `bg-black bg-opacity-50` → `bg-black/50` (hero overlay was a solid black box hiding the image).
- [resources/views/filament/app/pages/survey-dashboard.blade.php](../../resources/views/filament/app/pages/survey-dashboard.blade.php): 12× `bg-white bg-opacity-50` → `bg-white/50` (progress-bar tracks were solid white).

### `flex-shrink-0` → `shrink-0` (removed alias)
v4 dropped the deprecated `flex-shrink-*` alias, so the icon columns could shrink.
- [resources/views/components/red-alert-box.blade.php](../../resources/views/components/red-alert-box.blade.php)
- [resources/views/filament/app/pages/place-adaptations/initial-pilot.blade.php](../../resources/views/filament/app/pages/place-adaptations/initial-pilot.blade.php)
- [resources/views/filament/app/pages/pilot/pilot-index.blade.php](../../resources/views/filament/app/pages/pilot/pilot-index.blade.php)

### Bare border colour → explicit `border-gray-200`
v4 changed the default border colour from `gray-200` to `currentColor`. Added `border-gray-200` to width-only borders.
- [resources/css/filament/app/theme.css](../../resources/css/filament/app/theme.css): `.fi-ta-reorder-indicator` (`border`), `.dropdown_tables .fi-ta-ctn` (`border-b`), `.dropdown_tables .fi-ta-content` (`border-x`).
- [resources/views/team-translation-review.blade.php](../../resources/views/team-translation-review.blade.php) and [.../choice-list-entries-info.blade.php](../../resources/views/filament/app/clusters/lookup-tables/resources/choice-list-entry-resource/widgets/choice-list-entries-info.blade.php): divider `border-b` → `border-b border-gray-200`.
- Left as-is: `offline-action-section.blade.php` and `rounded-section.blade.php` — their `border-b` pairs with `.actionblockborder` which already sets `border-color: #bbbbbb`.

### `shadow-sm` → `shadow-xs` (cosmetic rescale)
v4 shifted the shadow scale (old `shadow-sm` is now `shadow-xs`), so existing `shadow-sm` rendered one step heavier.
- [resources/views/livewire/cover-page.blade.php](../../resources/views/livewire/cover-page.blade.php): 3 image cards.
- [resources/views/filament/stats-overview-widget/stat.blade.php](../../resources/views/filament/stats-overview-widget/stat.blade.php): published copy of Filament's stat widget view, realigned to the v5 scale.

### Verified, no change
`space-x-*` / `space-y-*` (~15 usages): v4 lowered their selector specificity but no layout shift was found; left untouched.

## Verification
`npm run build` is green (only the pre-existing Leaflet `public-map` chunk-size warning). Confirmed the compiled `theme.css` emits the new utilities: `.shrink-0`, `.shadow-xs`, `.border-gray-200`, `.bg-white/50`, `.bg-black/50`.

## Test impact
All edits are class-string substitutions; no PHP/JS behaviour changes, so the test suite is unaffected.

## Out of scope
The `packages/filament-odk-link` build mismatch (pins `tailwindcss ^3.3.3` while its CSS already uses v4 `@source`) was left untouched per decision during planning.
