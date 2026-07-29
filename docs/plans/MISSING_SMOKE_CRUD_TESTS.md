# Missing Smoke & CRUD Tests

**Status:** Completed — see [docs/change-logs/missing-smoke-crud-tests.md](../change-logs/missing-smoke-crud-tests.md). All planned tests implemented (85 smoke/CRUD tests passing). The optional `HddsHints` smoke test was skipped: the page 404s without an HDDS module version linked to a deployed xlsform. The Program record-URL test uses `assertSeeHtml()` instead of `getRecordUrl()` for phpstan compatibility.

Follow-up to [SMOKE_TESTS.md](SMOKE_TESTS.md) and [CRUD_TESTS.md](CRUD_TESTS.md). Audit date 2026-07-29: all 77 existing tests in `tests/Feature/Smoke/` and `tests/Feature/Crud/` pass. This plan adds the tests from the two original plans that are still missing **and still feasible against the current codebase**, and fixes two vacuous tests found during the audit.

---

## Audit summary — why most "missing" items are not in this plan

The codebase has moved on since both plans were written. Items from the original plans that are now obsolete (no test to write):

| Original plan item | Why obsolete |
|---|---|
| Smoke: `cover page loads`, `results page loads`, `temp results page loads` | `CoverPage`/`ResultsPage`/temp-results routes removed; `/` now redirects to `/app` (`routes/web.php:14`). Adapted test added below. |
| Smoke: `data-collection-index`, `lisp-index`, `lisp-indicators`, `lisp-workshop` pages | Page classes deleted; `Pages/Lisp/` now contains only `OptionalModules` (already tested). |
| Smoke: `domains`, `themes`, `global-indicators` admin lists | `DomainResource`/`ThemeResource`/`GlobalIndicatorResource` no longer exist anywhere in app or packages. |
| CRUD: Domain (7 tests), Theme (7), GlobalIndicator (7) | Same — resources removed. |
| CRUD: User create/edit (4 tests) | Package redesign: Admin `UserResource` is list-only with an "invite users" action. Adapted tests already exist in `AdminPanelCrudTest.php`. |
| CRUD: XlsformModule `can create` + `create requires name` | `getHeaderActions()` (CreateAction) is commented out in the package's `ManageXlsformModule.php` — no create action exists to test. |
| CRUD: Program panel ProgramResource CRUD (6 tests) | Program panel redesigned around the `ManageProgram` tenant-profile page; adapted tests already exist in `ProgramPanelCrudTest.php`. |

After this plan completes, update the **Status** lines of both original plan docs to **Completed**, noting the obsolete items above, and link a shared change log.

---

## File 1 — `tests/Feature/Smoke/PublicRoutesTest.php` (1 test)

| Test | Implementation |
|---|---|
| `root path redirects to app login` | `$this->get('/')->assertRedirect('app')` — adapted from the plan's "cover page loads"; `/` is now a redirect (`routes/web.php:14`). |

Optional (not in original plan, flagged during audit): the only routable App-panel page with no smoke test is `HddsHints` (`/app/{team}/hdds-hints`, navigation hidden but discovered/routed). Add `hdds hints page loads` to `AppPanelTest.php` if it mounts cleanly without survey data; skip with a comment if it requires a deployed xlsform.

---

## File 2 — `tests/Feature/Crud/AdminPanelCrudTest.php` (4 tests + 2 fixes)

Actor/setup: existing `createSuperAdmin()` + `withAdminPanel()` `beforeEach` blocks.

### Dataset (2 tests)

Form fields (package `Datasets/Schemas/DatasetForm.php`): `name` required, `description` required. The `custom_key`/`label` block noted as failing in [fix-admin-crud-test-imports.md](../change-logs/fix-admin-crud-test-imports.md) is now commented out in the package, so plain create/edit works.

| Test | Implementation |
|---|---|
| `create dataset requires name` | `livewire(CreateDataset::class)->fillForm(['name' => '', 'description' => 'x'])->call('create')->assertHasFormErrors(['name' => 'required'])` |
| `can edit dataset` | `Dataset::forceCreate(['name' => 'Editable Dataset', 'primary_key' => 'id'])` (same as existing edit-page-loads test), then `livewire(EditDataset::class, ['record' => $dataset->id])->fillForm(['name' => 'Updated Dataset', 'description' => 'x'])->call('save')->assertHasNoFormErrors()` + `assertDatabaseHas` |

### Program (1 test)

| Test | Implementation |
|---|---|
| `program list links to program panel` | Create a `Program`, then assert the table record URL: `$table = livewire(ListPrograms::class)->instance()->getTable(); expect($table->getRecordUrl($program))->toBe(url('program/'.$program->id));` — the `recordUrl` closure is at `app/Filament/Admin/Resources/ProgramResource.php:25`. |

### XlsformModule (1 test)

Table (package `XlsformModules/Tables/XlsformModuleTable.php`) exposes `EditAction` (row) and `DeleteBulkAction` (grouped bulk) — no row DeleteAction, no create.

| Test | Implementation |
|---|---|
| `can delete xlsform module` | Create module via `XlsformModule::forceCreate([...])` (template chain as in existing `beforeEach`), then `livewire(ManageXlsformModule::class)->callTableBulkAction(DeleteBulkAction::class, [$module])` + `assertDatabaseMissing('xlsform_modules', ...)` |

### Fixes — vacuous tests in the `XlsformModuleVersion` describe block

Two tests currently pass without testing anything:

1. `can create xlsform module version` (second one, [AdminPanelCrudTest.php:224](../../tests/Feature/Crud/AdminPanelCrudTest.php#L224)) — fills module fields (`xlsform_template_id`, `label`) on the **version** create action, then asserts `assertDatabaseHas('xlsform_modules', ['name' => 'test_module'])`, which passes because `beforeEach` already created that exact module. Delete it — the first `can create default xlsform module version` test already covers version creation.
2. `can delete xlsform module` ([AdminPanelCrudTest.php:236](../../tests/Feature/Crud/AdminPanelCrudTest.php#L236)) — creates an `XlsformModuleVersion` but asserts `assertDatabaseMissing('xlsform_modules', ...)`, which passes vacuously (wrong table). Rework as `can delete xlsform module version`: assert against `xlsform_module_versions`, and verify the delete action actually exists on the version table (if not, use `callTableBulkAction`).

---

## File 3 — `tests/Feature/Crud/AppPanelCrudTest.php` (4 tests)

### Farm (1 test)

`FarmResource` table has `EditAction` (row) + `DeleteBulkAction` (toolbar) — plan's row-delete adapted to bulk delete. No `Farm` factory exists; build the parent chain manually:

```php
$level = new LocationLevel(); $level->name = 'Village'; $level->owner_id = $this->team->id; $level->save();
$location = Location::create(['location_level_id' => $level->id, 'name' => 'Test Village', 'code' => 'tv1']); // verify Location columns at implementation time
$farm = Farm::create(['owner_id' => $this->team->id, 'location_id' => $location->id, 'team_code' => 'farm-001']);
```

| Test | Implementation |
|---|---|
| `can bulk delete farm` | `withAppTenant($this->team)` + `livewire(ListFarms::class)->callTableBulkAction(DeleteBulkAction::class, [$farm])` + `assertDatabaseMissing('farms', ['id' => $farm->id])` |

### ChoiceListEntry (3 tests)

Existing `beforeEach` already builds the Template → Module → ModuleVersion → `smoke_test_list` ChoiceList chain. `ListChoiceListEntries::mount()` falls back to the first localisable choice list when `choiceListName` is not passed, so plain `livewire(ListChoiceListEntries::class)` resolves to `smoke_test_list`.

Form ([ChoiceListEntryResource.php:117](../../app/Filament/App/Clusters/Localisations/Resources/ChoiceListEntryResource.php#L117)): `name` required, plus required per-locale `text` inputs generated from the survey's languages. In tests with no languages configured the repeater block should be empty — verify at implementation time; if a locale row is required, seed one `Locale` first.

Edit/Delete row actions are visible only when `!$record->is_global_entry` — create the target entry with `is_global_entry => false` (team-owned).

| Test | Implementation |
|---|---|
| `can create choice list entry via table action` | `livewire(ListChoiceListEntries::class)->callAction(CreateAction::class, data: ['name' => 'new_entry', ...])->assertHasNoActionErrors()` + `assertDatabaseHas` (create is a header action, not a row action) |
| `can edit choice list entry via table action` | Create entry, then `callTableAction(EditAction::class, $entry, data: ['name' => 'updated_entry'])` + DB check |
| `can delete choice list entry via table action` | Create entry, then `callTableAction(DeleteAction::class, $entry)` + `assertDatabaseMissing` |

---

## Out of scope

- `FarmEntityResource` (list/create/edit/import pages) — post-dates both plans; only the column-visibility test exists. Candidate for a separate smoke/CRUD plan.
- Package-level gaps (no create action on `ManageXlsformModule`, no user create/edit pages) — behaviour changes belong in the packages, not in this test plan.

## Completion checklist

1. All new/reworked tests pass: `./vendor/bin/pest tests/Feature/Smoke tests/Feature/Crud`
2. Update **Status** in `SMOKE_TESTS.md` and `CRUD_TESTS.md` to Completed, noting obsolete items.
3. Write change log in `docs/change-logs/` referencing this plan and the two originals.
