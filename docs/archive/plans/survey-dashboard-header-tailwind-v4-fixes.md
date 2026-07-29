# Survey Dashboard Header — Tailwind v4 / Filament 5 Regression Fixes

**Status:** Completed — see [change log](../../change-logs/survey-dashboard-header-tailwind-v4-fixes.md).

## Context

The `filament-5` branch upgraded Tailwind CSS v3 → v4 **and** Filament 3 → 5 together. The App-panel Survey Dashboard header and top navigation regressed visually (`temp/tailwind3.png` before vs `temp/tailwind4.png` after). A prior pass (`tailwind-v4-template-class-cleanup.md`) handled opacity/border/shadow utility renames but not the cascade and topbar-layout regressions here.

Root cause for the text issues: in `resources/css/filament/app/theme.css` the global bare element rules (`h1`–`h6`, `p`, `button`, and the `body:has(#languages),.actionblock` block) were written **outside any cascade layer**. In Tailwind v4 all utilities live in `@layer utilities`; per the cascade-layer spec, unlayered rules beat any layered rule. So `text-white` / `text-green` utilities lost to those element rules. In v3 they sat in `@layer base`, so utilities won.

## Differences → cause → fix

| # | Difference (before → after) | Cause | Fix |
|---|------------------------------|-------|-----|
| 1 | Nav items wrapped to 2 rows | Filament 5 `.fi-topbar-nav-groups` adds `lg:flex-wrap` | `.fi-topbar-nav-groups` override → `lg:flex-nowrap` |
| 2 | "My Account" white-on-white text | Unlayered `button { text-white }` | Fixed by `@layer base` move |
| 3 | Change Language lighter orange | `->colors()` commented; default primary shade changed | Register `orange` (`#C45D5D`); button `color="orange"` |
| 4 | Avatar / Change Language overlap | Filament 5 `.fi-topbar-end` layout | `.fi-topbar-end` gap + `shrink-0` on children |
| 5 | Headings grey not white | Unlayered `h1/h2 { text-brown }` | `@layer base` move |
| 6 | Shortcut text dark not white | Unlayered `h3/p { text-brown }` | `@layer base` move |

## Changes made

1. **`resources/css/filament/app/theme.css`** — wrapped the bare element rules (`body`, `body:has(#surveydash)`, the `body:has(#languages),.actionblock` block, `h1`–`h6`, `p`, `button`) in `@layer base`; left all `.fi-*`/`.class` rules unlayered. Removed the now-redundant `button.text-white:not(:hover) span { color: white !important }` hack. Added `.fi-topbar-nav-groups { lg:flex-nowrap; white-space:nowrap }` and `.fi-topbar-end { gap-x-4; > * { shrink-0 } }` overrides.
2. **`app/Providers/Filament/AppPanelProvider.php`** — `use Filament\Support\Colors\Color;` and `->colors(['orange' => Color::hex('#C45D5D')])`.
3. **`resources/views/languageSelector.blade.php`** — `<x-filament::button color="orange">`.

## Verification

- `npm run build` green.
- Compiled `theme-*.css` confirmed: element brown rules sit in `@layer base` (early offset) while `.text-white` sits in `@layer utilities` (later) → utilities now win. `fi-btn-color-orange` emitted.
- Manual visual spot-check against `temp/tailwind3.png` still recommended (headings/Shortcut white, single-row nav, legible My Account, no avatar overlap, darker orange button), plus a pass over Filament tables/modals/action-blocks and the `body:has(#languages)` survey-language screens for any previously-masked-utility shifts.
