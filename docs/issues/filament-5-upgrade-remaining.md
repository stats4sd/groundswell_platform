# Filament 5 upgrade — remaining issues / blockers

State at hand-off after the combined Filament 5 / Livewire 4 / Laravel 13 update landed (see [change log](../change-logs/filament-5-livewire-4-laravel-13-upgrade.md)). The app boots; `./vendor/bin/pest` reports **33 passed, 76 failed**. The failures cluster into a small number of root causes, listed below by likely owner. Counts are approximate (one root cause fans out across several tests).

## A. Missing `program_members` table — ~24 failures (largest cluster)

**Symptom:** `SQLSTATE[HY000]: General error: 1 no such table: program_members` across `AppPanelTest`, `AdminPanelTest`, `ProgramPanelTest`, `ProgramPanelCrudTest` and any view touching program membership.

**Cause:** `packages/filament-team-management` ships the migration only as a **stub** — `database/migrations/6_create_program_members_table.php.stub`. Laravel loads `*.php`, not `*.php.stub`, so the table is never created in the test DB (or anywhere). The F5 restructure of team-management moved Program membership to a `program_members` table, but the host app has not published/created it.

**Likely fix (host app):** publish the team-management migrations so `program_members` (and any sibling restructured tables) are created, e.g. `php artisan vendor:publish --tag=filament-team-management-migrations` (confirm the exact tag), then verify `DatabaseSeeder` + the in-memory test DB pick them up. Alternatively the package should `loadMigrationsFrom(...)` for these — coordinate with the team-management owner.

## B. `program_team` pivot resolves a class name into a column — ~3 failures

**Symptom:** `SQLSTATE[HY000]: General error: 1 no such column: program_team.Stats4sd\FilamentTeamManagement\Models\Program`.

**Cause:** a relationship/pivot in team-management (or a host-app model extending it) is passing a model **class string** where a pivot column name is expected — the FQCN is leaking into the SQL. Probably a `belongsToMany`/pivot definition whose argument order changed, surfaced by Laravel 13 / the F5 package restructure.

**Owner:** team-management package (verify against the host `Team`/`Program` models).

## C. odk-link package not fully Filament-5-migrated — ~10 failures

The plan's pre-flight gate assigned "finish odk-link's stragglers" to Dave and treats a compatible package as a prerequisite. The checked-out `packages/filament-odk-link` declares `filament/filament: ^5.0` and migrated its forms/schemas, but **table actions and some pages were not migrated**:

- **Table-action namespaces:** `Class "Filament\Tables\Actions\EditAction" not found` (e.g. `packages/filament-odk-link/src/Filament/OdkAdmin/Resources/ChoiceListResource.php:92`), plus `Action [Filament\Tables\Actions\CreateAction] not found on table` and `[Filament\Tables\Actions\DeleteBulkAction] not found`. In Filament 4/5 these moved to `Filament\Actions\*`. Reached via a `use Filament\Tables;` + `Tables\Actions\…` alias, so they were missed by a grep for the full namespace.
- **Resource pages not registered as Livewire components:** `Unable to find component: [Stats4sd\FilamentOdkLink\…\XlsformModuleResource\Pages\ManageXlsformModule]`, `…\XlsformModuleVersionResource\Pages\ManageXlsformModuleVersion`, `…\DatasetResource\Pages\CreateDataset`.
- **Relationship resolves to a null class:** `Class name must be a valid object or a string` originating around `packages/filament-odk-link/src/Models/OdkLink/XlsformLanguages/Locale.php` (`newRelatedInstance(NULL)`) — a `config('filament-odk-link.models.…')`-driven relationship returning null. Surfaced under Laravel 13.
- **Schema/migration mismatch:** `table xlsform_modules has no column named row_names` (insert in `XlsformModuleImport`) — a migration is missing the `row_names` column the importer writes.

**Fix:** run the Filament v4 → v5 codemods over `packages/filament-odk-link/src` and reconcile the `row_names` migration, in the odk-link repo (submodule), then bump the pointer. Out of the root-app scope per the plan.

## D. Stale tests for removed Theme / Domain / GlobalIndicator — ~20 failures

**Symptom:** `Class "App\Models\Holpa\Theme" not found`, `…\Holpa\Domain not found`, and `ComponentNotFoundException` for `ThemeResource\Pages\CreateTheme`, `DomainResource\Pages\CreateDomain`, etc.

**Cause:** commit `4bde686 "removes domains, themes, indicators"` deleted those models and Filament resources, but `tests/Feature/Crud/AdminPanelCrudTest.php` still imports `App\Models\Holpa\{Theme,Domain,GlobalIndicator}` and the deleted resource pages, and runs whole `describe('Admin panel CRUD — Theme'|'— Domain'|'— GlobalIndicator')` blocks against them. **Pre-existing test debt, not caused by the upgrade** (the suite could not run before the update, so it was never caught).

**Fix (host app, in scope, simple):** delete the stale imports (lines ~3–16) and the three `describe(...)` blocks for Domain / Theme / GlobalIndicator from `AdminPanelCrudTest.php`. This was started but intentionally left out of this pass per the request to summarise rather than fix.

## E. `MonitorDataCollection.php:68` — "Attempt to read property id on null"

Seen as a secondary error during App-panel route smoke tests once the `program_members` failure (A) is in play. Re-check after A is fixed — it may be a downstream symptom rather than an independent bug. File: `app/Filament/App/Pages/DataCollection/MonitorDataCollection.php:68`.

## Plan steps not yet started

- **Step 5 — Tailwind v4 theme migration.** `resources/css/filament/app/theme.css` + `tailwind.config.js` still on Tailwind 3.4. Needs upgrade to Tailwind 4.1+, `@config` → `@source`/CSS-config, the custom palette + Montserrat port, and `npm run build` verification. The plan calls this the fiddliest frontend task.
- **Step 4 — remaining F4 manual audits** (not yet verified; tests will help confirm): table filters now **deferred by default** (decide per-table vs panel-wide `deferFilters(false)`); `unique()` now ignores the current record by default (`ignoreRecord` audit); enum fields always return enum instances; URL-param renames (`activeTab`→`tab`, `tableFilters`→`filters`, `tableSort`→`sort`, `activeRelationManager`→`relation`); F4 tenancy auto-scoping possibly double-applying with `SetLatest*Middleware`.
- **Step 6 — config**: `php artisan vendor:publish --tag=filament-config`; optional `filament:upgrade-directory-structure-to-v4`.
- **Validation tail**: `phpstan` (larastan v3) and `pint` not yet run.

## Housekeeping

- Remove the codemod tool when done: `composer remove --dev filament/upgrade`.
- Commit the `packages/laravel-shiny-loader/composer.json` `illuminate ^13` widening in the shiny-loader repo and bump the submodule pointer (currently an uncommitted in-submodule change — see change log).
