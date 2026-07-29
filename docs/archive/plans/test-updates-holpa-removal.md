# Test updates for HOLPA content removal (PRs #14, #22)

**Status:** Completed — see [change log](../../change-logs/test-updates-holpa-removal.md)

## Background

PR #14 ("updates branding", merged 2026-05-29) and PR #22 ("Removes old content & fixes translations", merged 2026-06-01) deleted the HOLPA-specific domain from the app, including:

- Models: `App\Models\Holpa\{Theme, Domain, GlobalIndicator, LocalIndicator}` and their factories/migrations/seeders (`ThemesAndIndicatorsSeeder`).
- Admin Filament resources: `ThemeResource`, `DomainResource`, `GlobalIndicatorResource`, `DietDiversityModuleVersionResource` and their pages/relation managers.
- App pages: `PlaceAdaptations\DietDiversity` (PR #14), `PlaceAdaptations\TimeFrame` (PR #22).
- SurveyData / Reference models and tables: `Fish`, `FishUse`, `EcologicalPractice`, `Product`, `PermanentWorker`, `SeasonalWorkerSeason`, `LivestockUse`, `FieldworkSite`, `GrowingSeason`, `AgPractice(Group)`, `Currency`, `ExchangeRate`, `GniEntry`, `ClimateMitigationScore`, plus the `TestMiniForms`/`TestRealForms` seeder sets.

The test suite still references some of the removed classes/routes and fails to run as a result. This plan covers only the test changes needed to reflect the removals.

## Scope boundary

These suite failures are **out of scope** — they are Filament 5 migration breakage on the `filament-5` branch, not HOLPA removal:

- `Class "Filament\Tables\Actions\EditAction" not found` (e.g. `/admin/choice-lists` smoke test).
- `Filament\Actions\CreateAction` / `Filament\Tables\Actions\DeleteBulkAction` namespace moves used in CRUD tests.

These should be handled by the Filament 5 upgrade work, not here.

## Confirmed NOT affected (no change needed)

- `tests/Feature/PlaceAdaptations/HddsHintsTest.php` — `HddsHints` page still exists; the deleted `DietDiversity`/`TimeFrame` pages have no tests.
- `tests/Feature/Smoke/AppPanelTest.php` — PR #22 already removed the `time-frame` route test; current file is clean.
- `tests/Feature/Smoke/PublicRoutesTest.php` — `PreviousResult` model, `previous_agroecology_scores`/`temp_results` tables and `/temp-results` still exist on this branch; all 7 tests pass. (Optional: the stale HOLPA comment at lines 22–24 can be deleted, but it is harmless.)
- `tests/Feature/Crud/AppPanelCrudTest.php`, `tests/Feature/Crud/ProgramPanelCrudTest.php`, `tests/Feature/XlsformTemplateImportTest.php`, `tests/Architecture/ArchTest.php` — no HOLPA references.

## Changes

### 1. `tests/TestCase.php`

Loaded by every Feature test via `pest()->extend(Tests\TestCase::class)`.

- Remove `use App\Models\Holpa\Theme;` (line 5).
- Remove the `public Theme $theme;` property (line 25).

No replacement — nothing in the remaining suite relies on a seeded `$this->theme`.

### 2. `tests/Feature/Crud/AdminPanelCrudTest.php`

Delete the three `describe` blocks for removed admin resources, then their now-unused imports:

- `describe('Admin panel CRUD — Domain', ...)` (lines 31–93).
- `describe('Admin panel CRUD — Theme', ...)` (lines 97–158).
- `describe('Admin panel CRUD — GlobalIndicator', ...)` (lines 162–222).

Remove the corresponding imports (lines 3–16):

- `DomainResource\Pages\{CreateDomain, EditDomain, ListDomains}`
- `GlobalIndicatorResource\Pages\{CreateGlobalIndicator, EditGlobalIndicator, ListGlobalIndicators}`
- `ThemeResource\Pages\{CreateTheme, EditTheme, ListThemes}`
- `App\Models\Holpa\{Domain, GlobalIndicator, Theme}`

Keep the remaining blocks (Program, Team, User, Dataset, XlsformTemplate, XlsformModule, XlsformModuleVersion) and their imports (`TeamResource` pages, `Team`, `User`, `CreateAction`, `DeleteBulkAction`, `Http`, ODK-link imports, `ListUsers`). Note: `CreateAction`/`DeleteBulkAction` use the old Filament namespaces and will still fail until the F5 work lands — that is the out-of-scope item above, not part of this change.

### 3. `tests/Feature/Smoke/AdminPanelTest.php`

Delete the three smoke tests hitting removed admin routes (all would now 404):

- `test('domains list loads', ...)` (lines 15–19).
- `test('global indicators list loads', ...)` (lines 21–25).
- `test('themes list loads', ...)` (lines 27–31).

Keep the rest.

## Verification

After the edits:

```bash
./vendor/bin/pest tests/Feature/Crud/AdminPanelCrudTest.php
./vendor/bin/pest tests/Feature/Smoke/AdminPanelTest.php
./vendor/bin/pest tests/TestCase.php   # via full suite
```

Expected: no remaining failures referencing `Holpa\*`, `domains`, `themes`, or `global-indicators`. Any residual failures should be only the Filament 5 namespace issues noted under Scope boundary.

## Out of scope / follow-up

- Filament 5 action-namespace fixes across the CRUD/smoke tests (`EditAction`, `CreateAction`, `DeleteBulkAction`).
- Optional cleanup of the stale HOLPA comment in `PublicRoutesTest.php`.
