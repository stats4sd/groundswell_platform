# Change Log — Survey Dashboard Header Tailwind v4 / Filament 5 Fixes

Implements [docs/plans/survey-dashboard-header-tailwind-v4-fixes.md](../plans/survey-dashboard-header-tailwind-v4-fixes.md).

## Summary

Fixed App-panel Survey Dashboard header/topbar regressions introduced by the combined Tailwind v3→v4 and Filament 3→5 upgrade.

## Files changed

- **`resources/css/filament/app/theme.css`**
  - Moved global bare element rules (`body`, `body:has(#surveydash)`, the `body:has(#languages),.actionblock` typography block, `h1`–`h6`, `p`, `button`) into `@layer base`. Under Tailwind v4 these were unlayered and overrode utilities; now `text-white`/`text-green` utilities win again. This restores white headings ("Groundswell International" / "Survey Dashboard"), the white "Shortcut" text, the header PREVIEW button's green text, and the legible "My Account" topbar label.
  - Removed the redundant `button.text-white:not(:hover) span { color: white !important }` workaround.
  - Added `.fi-topbar-nav-groups { @apply lg:flex-nowrap lg:gap-y-0; white-space: nowrap }` to restore the single-row top nav (Filament 5 introduced `lg:flex-wrap`).
  - Added `.fi-topbar-end { @apply gap-x-4; > * { @apply shrink-0 } }` to stop the user-menu avatar and Change Language button overlapping.

- **`app/Providers/Filament/AppPanelProvider.php`**
  - Imported `Filament\Support\Colors\Color` and registered `->colors(['orange' => Color::hex('#C45D5D')])` to restore the brand (darker) orange.

- **`resources/views/languageSelector.blade.php`**
  - Set the Change Language button to `color="orange"`.

## Notes

- No PHP/JS behaviour changes; the Pest suite is unaffected.
- `npm run build` passes. Compiled CSS verified: element rules in `@layer base`, `.text-white` in `@layer utilities`, `fi-btn-color-orange` present.
