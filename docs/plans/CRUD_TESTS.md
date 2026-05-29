# CRUD Test Plan

## Overview

Tests that each Filament resource can list, create, edit, delete, and view records correctly. Tests live in `tests/Feature/Crud/`.

**Scope:** Happy-path create/edit/delete for each resource, plus one validation failure test per create form. Page-load assertions from the smoke tests are **not** repeated here.

**Out of scope:**
- Resources requiring file uploads (`DietDiversityModuleVersionResource`, `XlsformTemplateResource`, `XlsformModuleVersionResource`) — tested at page-load level only; full create requires a real xlsx file.
- `ChoiceListResource` — no create page; edit form is simple enough to leave for a dedicated ODK test suite.
- `SubmissionResource` view page — requires real ODK submission data; already tested structurally in smoke tests.

---

## Test Infrastructure

### New helpers needed in `tests/Pest.php`

```php
// Tenant helper for app-panel livewire tests
function withAppTenant(\App\Models\Team $team): void
{
    \Filament\Facades\Filament::setTenant($team);
}

// Tenant helper for program-panel livewire tests
function withProgramTenant(\Stats4sd\FilamentTeamManagement\Models\Program $program): void
{
    \Filament\Facades\Filament::setTenant($program);
}
```

### New `beforeEach` patterns

**Admin tests** — reuse `createSuperAdmin()` from smoke-test helpers.

**App tests** — reuse `createAppUser($team)` + `Team::withoutEvents()` pattern, plus `withAppTenant($team)` before each `livewire()` call.

**Program tests** — reuse `createProgramAdmin($program)`.

### Livewire call conventions (Filament 3)

| Operation | Call |
|---|---|
| Create (separate page) | `livewire(CreateX::class)->fillForm([...])->call('create')->assertHasNoFormErrors()` |
| Edit (separate page) | `livewire(EditX::class, ['record' => $record->id])->fillForm([...])->call('save')->assertHasNoFormErrors()` |
| Create (ManagePage inline) | `livewire(ManageX::class)->callTableAction('create', data: [...])->assertSuccessful()` |
| Edit (ManagePage inline) | `livewire(ManageX::class)->callTableAction('edit', $record, data: [...])->assertSuccessful()` |
| Delete (any table) | `livewire(ListX::class)->callTableAction(DeleteAction::class, $record)->assertSuccessful()` |

---

## File 1 — `tests/Feature/Crud/AdminPanelCrudTest.php`

**Actor:** `$this->superAdmin = createSuperAdmin()` in `beforeEach`.
All `livewire()` calls preceded by `$this->actingAs($this->superAdmin)`.

---

### Domain (14 tests)

Prerequisites: `ThemesAndIndicatorsSeeder` runs via `DatabaseSeeder` — domains already exist in test DB.

| Test name | Assertion |
|---|---|
| `domain list shows existing records` | `livewire(ListDomains::class)->assertCanSeeTableRecords(Domain::all())` |
| `domain create page loads` | `get('/admin/domains/create')->assertOk()` |
| `can create domain` | `livewire(CreateDomain::class)->fillForm(['name'=>'New Domain'])->call('create')->assertHasNoFormErrors()` + `assertDatabaseHas` |
| `create domain requires name` | `->fillForm(['name'=>''])->call('create')->assertHasFormErrors(['name'=>'required'])` |
| `domain edit page loads` | `get('/admin/domains/{id}/edit')->assertOk()` |
| `can edit domain` | `livewire(EditDomain::class, ['record'=>$id])->fillForm(['name'=>'Updated'])->call('save')->assertHasNoFormErrors()` + DB check |
| `edit domain requires name` | `->fillForm(['name'=>''])->call('save')->assertHasFormErrors(['name'=>'required'])` |

---

### Theme (7 tests)

Prerequisites: `Domain` records seeded by `ThemesAndIndicatorsSeeder`.

| Test name | Assertion |
|---|---|
| `theme list shows existing records` | `assertCanSeeTableRecords` |
| `theme create page loads` | `assertOk()` |
| `can create theme` | `fillForm(['name'=>'Test Theme','module'=>'Context','domain_id'=>$domain->id])->call('create')->assertHasNoFormErrors()` + DB check |
| `create theme requires name` | `assertHasFormErrors(['name'=>'required'])` |
| `theme edit page loads` | `assertOk()` |
| `can edit theme` | `fillForm(['name'=>'Updated Theme'])->call('save')->assertHasNoFormErrors()` + DB check |
| `can delete theme` | `callTableAction(DeleteAction::class, $theme)->assertSuccessful()` + `assertDatabaseMissing` |

---

### GlobalIndicator (7 tests)

Prerequisites: `Theme` records seeded.

| Test name | Assertion |
|---|---|
| `global indicator list shows existing records` | `assertCanSeeTableRecords` |
| `global indicator create page loads` | `assertOk()` |
| `can create global indicator` | `fillForm(['name'=>'Test Indicator','theme_id'=>$theme->id])->call('create')->assertHasNoFormErrors()` + DB check |
| `create global indicator requires theme` | `assertHasFormErrors(['theme_id'=>'required'])` |
| `global indicator edit page loads` | `assertOk()` |
| `can edit global indicator` | `fillForm(['name'=>'Updated Indicator'])->call('save')->assertHasNoFormErrors()` + DB check |
| `can delete global indicator` | `assertSuccessful()` + `assertDatabaseMissing` |

---

### Program (2 tests — list-only resource in Admin panel)

The app-level `ProgramResource` overrides `getPages()` to expose only `index`. Create/edit are handled via the Program panel directly.

| Test name | Assertion |
|---|---|
| `program list shows existing programs` | `livewire(ListPrograms::class)->assertSuccessful()` |
| `program list links to program panel` | table `recordUrl` resolves to `/program/{id}` |

---

### Team (5 tests)

> `Team::create` fires the `created` boot hook which calls ODK Central. Wrap in `Http::fake()`.

| Test name | Assertion |
|---|---|
| `team list shows existing teams` | `assertCanSeeTableRecords` |
| `team create page loads` | `get('/admin/teams/create')->assertOk()` |
| `can create team` | `Http::fake()` + `fillForm(['name'=>'Test Team'])->call('create')->assertHasNoFormErrors()` + DB check |
| `team view page loads` | `get('/admin/teams/{id}')->assertOk()` |
| `team edit page loads` | `get('/admin/teams/{id}/edit')->assertOk()` |

> Full edit test omitted — saving a team may re-trigger ODK project sync depending on dirty fields; covers a large surface area better suited to a dedicated integration test.

---

### User (6 tests)

| Test name | Assertion |
|---|---|
| `user list shows existing users` | `assertCanSeeTableRecords` |
| `user create page loads` | `get('/admin/users/create')->assertOk()` |
| `can create user` | `fillForm(['name'=>'Test User','email'=>'test@example.com','password'=>'password'])->call('create')->assertHasNoFormErrors()` + DB check |
| `create user requires email` | `assertHasFormErrors(['email'=>'required'])` |
| `user edit page loads` | `get('/admin/users/{id}/edit')->assertOk()` |
| `can edit user` | `fillForm(['name'=>'Updated Name'])->call('save')->assertHasNoFormErrors()` + DB check |

---

### Dataset (6 tests)

| Test name | Assertion |
|---|---|
| `dataset list page loads` | `get('/admin/datasets')->assertOk()` |
| `dataset create page loads` | `get('/admin/datasets/create')->assertOk()` |
| `can create dataset` | `fillForm(['title'=>'Test Dataset'])->call('create')->assertHasNoFormErrors()` + DB check |
| `create dataset requires title` | `assertHasFormErrors(['title'=>'required'])` |
| `dataset edit page loads` | `get('/admin/datasets/{id}/edit')->assertOk()` |
| `can edit dataset` | `fillForm(['title'=>'Updated Dataset'])->call('save')->assertHasNoFormErrors()` + DB check |

---

### XlsformTemplate (2 tests — file upload required for create)

| Test name | Assertion |
|---|---|
| `xlsform template list page loads` | `get('/admin/xlsform-templates')->assertOk()` |
| `xlsform template create page loads` | `get('/admin/xlsform-templates/create')->assertOk()` |

> Full create test **out of scope** — requires `.xlsx` file upload.

---

### XlsformModule (4 tests — ManagePage)

| Test name | Assertion |
|---|---|
| `xlsform module list page loads` | `get('/admin/xlsform-modules')->assertOk()` |
| `can create xlsform module` | `callTableAction('create', data: ['label'=>'Test Module','name'=>'test_module'])->assertSuccessful()` + DB check |
| `create xlsform module requires name` | `callTableAction('create', data: ['label'=>'Test','name'=>''])->assertHasTableActionErrors(['name'])` |
| `can delete xlsform module` | `callTableAction(DeleteAction::class, $module)->assertSuccessful()` + `assertDatabaseMissing` |

---

### XlsformModuleVersion (3 tests — ManagePage, file upload for non-default)

| Test name | Assertion |
|---|---|
| `xlsform module version list page loads` | `get('/admin/xlsform-module-versions')->assertOk()` |
| `can create xlsform module version (default)` | `callTableAction('create', data: ['name'=>'v1','xlsform_module_id'=>$module->id,'is_default'=>true])->assertSuccessful()` |
| `can delete xlsform module version` | `callTableAction(DeleteAction::class, $version)->assertSuccessful()` |

---

## File 2 — `tests/Feature/Crud/AppPanelCrudTest.php`

**Actor:** `$this->team` + `$this->user = createAppUser($this->team)` in `beforeEach`.
All `livewire()` calls preceded by `$this->actingAs($this->user)` and `withAppTenant($this->team)`.

---

### Team — App panel (5 tests)

The App panel `TeamResource` lets a team member view and edit their own team.

| Test name | Assertion |
|---|---|
| `team list shows current team` | `livewire(ListTeams::class)->assertCanSeeTableRecords([$this->team])` |
| `team create page loads` | `get("/app/{$team->id}/teams/create")->assertOk()` |
| `can create team via app panel` | `Http::fake()` + `fillForm(['name'=>'New Team'])->call('create')->assertHasNoFormErrors()` + DB check |
| `team view page loads` | `get("/app/{$team->id}/teams/{$team->id}")->assertOk()` |
| `team edit page loads` | `get("/app/{$team->id}/teams/{$team->id}/edit")->assertOk()` |

---

### LocationLevel (3 tests — list and view only, no create/edit page)

| Test name | Assertion |
|---|---|
| `location level list page loads` | `get("/app/{$team->id}/location-levels/location-levels")->assertOk()` |
| `location level view page loads` | Create a `LocationLevel` record, then `get(".../location-levels/{id}")->assertOk()` |
| `can delete location level` | `livewire(ListLocationLevels::class)->callTableAction(DeleteAction::class, $level)->assertSuccessful()` |

---

### Farm (2 tests — list only)

| Test name | Assertion |
|---|---|
| `farm list page loads` | `get("/app/{$team->id}/location-levels/farms")->assertOk()` |
| `can delete farm` | `livewire(ListFarms::class)->callTableAction(DeleteAction::class, $farm)->assertSuccessful()` |

---

### ChoiceListEntry (4 tests — CRUD via table modal actions, no separate pages)

Prerequisites: `ChoiceList::forceCreate(['list_name'=>'smoke_test_list','is_localisable'=>true,'has_custom_handling'=>false])` (already in `beforeEach`).

| Test name | Assertion |
|---|---|
| `choice list entry list page loads` | `get("/app/{$team->id}/localisations/choice-list-entries")->assertOk()` |
| `can create choice list entry via table action` | `callTableAction('create', data: [...])->assertSuccessful()` + DB check |
| `can edit choice list entry via table action` | `callTableAction(EditAction::class, $entry, data: [...])->assertSuccessful()` + DB check |
| `can delete choice list entry via table action` | `callTableAction(DeleteAction::class, $entry)->assertSuccessful()` |

---

## File 3 — `tests/Feature/Crud/ProgramPanelCrudTest.php`

**Actor:** `$this->program` + `$this->programAdmin = createProgramAdmin($this->program)` in `beforeEach`.
All `livewire()` calls preceded by `$this->actingAs($this->programAdmin)` and `withProgramTenant($this->program)`.

---

### Program — Program panel (6 tests)

| Test name | Assertion |
|---|---|
| `program list shows current program` | `assertCanSeeTableRecords([$this->program])` |
| `program create page loads` | `get("/program/{$program->id}/programs/create")->assertOk()` |
| `can create program` | `fillForm(['name'=>'New Program'])->call('create')->assertHasNoFormErrors()` + DB check |
| `create program requires name` | `assertHasFormErrors(['name'=>'required'])` |
| `program edit page loads` | `get("/program/{$program->id}/programs/{$program->id}/edit")->assertOk()` |
| `can edit program` | `fillForm(['name'=>'Updated Program'])->call('save')->assertHasNoFormErrors()` + DB check |

---

## Test count summary

| File | Tests |
|---|---|
| `AdminPanelCrudTest.php` | ~49 |
| `AppPanelCrudTest.php` | ~14 |
| `ProgramPanelCrudTest.php` | ~6 |
| **Total** | **~69** |

---

## Gotchas

| Issue | Fix |
|---|---|
| `Team::create` triggers ODK Central sync | `Http::fake()` before any team creation in tests |
| App panel `livewire()` calls need a Filament tenant | Call `withAppTenant($this->team)` before each `livewire()` invocation |
| `DietDiversityModuleVersionResource` and `XlsformTemplateResource` require `.xlsx` upload | Skip full create/edit; test page loads only |
| `GlobalIndicatorFactory` exists but `DomainFactory` / `ThemeFactory` do not | Create domains and themes with `Domain::create([...])` directly; seeded data from `ThemesAndIndicatorsSeeder` can also be used |
| `ProgramResource` in Admin panel has no create/edit routes | Only test list; full CRUD tested in Program panel |
| Spatie permission cache bleeding between tests | Already handled by global `beforeEach` in `Pest.php` |
