# Change Log: Location & Farm Info Module Rewrite

Implements [docs/plans/location-and-farm-info-module-rewrite-implementation.md](../plans/location-and-farm-info-module-rewrite-implementation.md) (design: [docs/plans/location-and-farm-info-module-rewrite.md](../plans/location-and-farm-info-module-rewrite.md)).

**Branch:** `location-odk-module-builder` (app) + `add-new-farm-crud-for-odk-central-submodule` (filament-odk-link package)

## Summary

Replaced the legacy `App\Services\LocationSectionBuilder` with two focused, idempotent builders — `LocationsModuleBuilder` and `FarmInfoModuleBuilder` — that populate two separate team-owned XLSForm module versions (`Local Locations`, `Local Farm Info`), and taught the package's `Xlsform::syncWithTemplate()` a true full-replacement mode (`can_be_replaced`) so a team's own module version fully replaces the global default instead of sitting alongside it.

## Changes

### Package: `stats4sd/filament-odk-link` (commit `61d19a7`)

- `Xlsform::syncWithTemplate()` now handles `can_be_replaced` modules: only the team's `firstOrCreate`'d `"Local {name}"` `XlsformModuleVersion` is attached at the module's `default_order` — the global default version is never attached for that module. `can_be_extended` behavior (global + local at `default_order + 1`) and plain-module behavior are unchanged. Also dropped a dead `use (&$countModules)` closure capture.
- New test: `tests/Unit/Models/XlsformSyncWithTemplateTest.php` covering all three module flag combinations.

### App

- New `app/Services/XlsformModules/LocationsModuleBuilder.php` — `populate(Team $team)` finds-or-creates the team's `Local Locations` module version and rebuilds its survey rows (a `select_one locN` + `locN_name` calculate pair per location level, root-first, wrapped in a `location` group) and per-level `locN` choice lists (entries keyed by location id, `cascade_filter` = parent location id). Stale rows/lists from removed levels are deleted.
- New `app/Services/XlsformModules/FarmInfoModuleBuilder.php` — `populate(Team $team)` finds-or-creates the team's `Local Farm Info` module version and rebuilds its survey rows: a `select_one_from_file Farm_Summary.csv` farm picker (filtered on the deepest location level), one calculate row per global `farm_entities` identifier/property `DatasetVariable` pulling from the selected entity, and a confirmation note listing them. Stale calculate rows from removed variables are deleted.
- `Team::localiseXlsforms()` now calls both builders under the existing `has_updated_locations` gate; legacy `LocationSectionBuilder` deleted. The scratch `app:test-notice` command was updated to match.
- Added `has_updated_locations` to `Team::$casts` as boolean (was uncast, returned `0`/`1`).
- Added the missing `@return HasMany<Location, $this>` generic to `LocationLevel::locations()`.
- New tests: `tests/Feature/Services/LocationsModuleBuilderTest.php`, `tests/Feature/Services/FarmInfoModuleBuilderTest.php`, `tests/Feature/Models/TeamLocaliseXlsformsTest.php`.

## Deviations from the plan

1. **Label assertions moved from `properties` to `defaultLabel`.** The package's `HasLanguageStrings::saved()` hook extracts every `label::…` key out of a row's `properties` collection into `LanguageString` rows (which is what the XLSForm export reads), so the plan's test assertions against `properties['label::English (en)']` could never pass. The builders still write `label::` keys into `properties` (same mechanism the legacy builder used); the tests assert via `defaultLabel->text` instead.
2. **`orderedLevels()` queries `$team->locationLevels()->get()` instead of iterating the cached `$team->locationLevels` relation** — with the cached relation, a second `populate()` on the same Team instance rebuilt rows for levels deleted since the relation was first loaded, so stale-row cleanup never took effect within one process.
3. **Test helper names.** `makeLevel()`/`makeLocation()` collided with globals already defined in `OdkFarmEntityServiceLocationTest.php` (Pest test-file functions share one namespace) — renamed to `makeLocationLevel()`/`makeChildLocation()`.
4. **`Team::$casts` addition** (not in the plan) so `has_updated_locations` round-trips as a real boolean, matching its sibling flags and the plan's own test expectation.

## Verification

- Package suite: `vendor/bin/pest --no-coverage` — 167 passed, 1 pre-existing failure (`SheetWiringTest`, present on clean HEAD before this work; unrelated to this change). Note: the package's phpunit.xml requests coverage reports, so on machines without xdebug/pcov the suite must be run with `--no-coverage` or it exits without running anything.
- App suite: `./vendor/bin/pest` — 125 passed, 0 failures.
- PHPStan: no errors in any new/changed file (repo-wide count went from 195 to 188 thanks to the `locations()` generic).
- Pint run on all new/edited files.

## Known gaps (pre-existing, out of scope — see the plan's context notes)

- `has_updated_locations` is never set to `true` anywhere in the codebase, so the builders still won't run automatically in production; `localiseXlsforms()` is only reachable via `InitialPilot::mount()`.
- Creating the `Locations`/`Farm Info` `XlsformModule` rows (`can_be_replaced = true`) on templates, and the `Farm_Summary` entities-sheet row, are admin-panel tasks that must be done before any of this takes effect for real. Existing `Xlsform`s won't pick up new template modules until `has_latest_template` is flipped false or `syncWithTemplate()` is called on them.
