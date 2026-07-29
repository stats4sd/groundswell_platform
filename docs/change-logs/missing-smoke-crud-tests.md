# Change Log: Missing Smoke & CRUD Tests

Implements [docs/plans/MISSING_SMOKE_CRUD_TESTS.md](../plans/MISSING_SMOKE_CRUD_TESTS.md), the follow-up to [SMOKE_TESTS.md](../plans/SMOKE_TESTS.md) and [CRUD_TESTS.md](../plans/CRUD_TESTS.md). Adds the still-feasible tests from the two original plans and fixes two vacuous tests found during the 2026-07-29 audit. Both original plans are now marked **Completed**.

## Test count

`tests/Feature/Smoke` + `tests/Feature/Crud`: 77 → 85 tests, all passing (+9 new, 1 vacuous test deleted, 1 vacuous test reworked).

## `tests/Feature/Smoke/PublicRoutesTest.php`

- New: `root path redirects to app login` — `/` now redirects to `/app` (`routes/web.php`); adapted from the original plan's "cover page loads".
- Removed unused `CoverPage`/`ResultsPage` imports.

## `tests/Feature/Smoke/AppPanelTest.php`

- No `HddsHints` smoke test added (optional item flagged in the plan): its `mount()` aborts 404 unless the team has an HDDS module version linked to a deployed xlsform. Noted with a comment in the file.

## `tests/Feature/Crud/AdminPanelCrudTest.php`

New tests:

- Dataset: `create dataset requires name`, `can edit dataset`.
- Program: `program list links to program panel` — asserts the rendered list contains the `program/{id}` record URL (the `recordUrl` override in `app/Filament/Admin/Resources/ProgramResource.php`). The plan's `getRecordUrl()` approach was swapped for `assertSeeHtml()` because `livewire()->instance()` is typed as the base Livewire component, which fails phpstan.
- XlsformModule: `can delete xlsform module` via the table's grouped `DeleteBulkAction` (the table has no row DeleteAction and no create action).

Fixes to vacuous tests in the XlsformModuleVersion describe block:

- Deleted the duplicate `can create xlsform module version` test — it filled module fields on the version create action and asserted a row that the `beforeEach` had already created; `can create default xlsform module version` covers version creation.
- Reworked `can delete xlsform module` into `can delete xlsform module version` — it asserted against the wrong table (`xlsform_modules`), passing vacuously; it now creates a version linked to the `beforeEach` module and asserts `xlsform_module_versions` after the row `DeleteAction`.

## `tests/Feature/Crud/AppPanelCrudTest.php`

New tests:

- Farm: `can bulk delete farm` — builds the LocationLevel → Location → Farm chain manually (no Farm factory) and deletes via the toolbar `DeleteBulkAction` (the table has no row DeleteAction).
- ChoiceListEntry: `can create choice list entry via table header action`, `can edit choice list entry via table action`, `can delete choice list entry via table action`. All operate on a team-owned entry (`owner_id` set) because edit/delete row actions are hidden for global entries. Teams automatically get an English locale on creation (`Team::booted()`), so the `languageStrings` repeater requires exactly one label item — the create test passes it as form data; the edit test seeds it on the entry first.

## `tests/TestCase.php`

- Added `public ChoiceList $choiceList;` to the shared test-property list, following the existing pattern for `$team`/`$xlsformTemplate` etc. (phpstan resolves `$this->…` in Pest closures against `Tests\TestCase`).

## Verification

- `./vendor/bin/pest tests/Feature/Smoke tests/Feature/Crud` — 85 passed.
- `./vendor/bin/phpstan analyse tests/Feature/Smoke tests/Feature/Crud` — no new errors (8 pre-existing errors from stub files / unmatched ignore patterns are unchanged).
- `./vendor/bin/pint` — applied to changed files.
