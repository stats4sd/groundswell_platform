# XlsForm Module Versions — Deep Study

## Conceptual Purpose

A **Module** is a named group of XlsForm survey questions (e.g. `diet_diversity`, `local_context`). A **Module Version** is one concrete set of those questions — the "Global" default plus any country-specific or team-specific alternatives. The `selected_xlsform_module_versions` pivot stitches chosen versions together into a deployable `Xlsform`.

---

## Database Schema (3 tables)

| Table | Purpose |
|---|---|
| `xlsform_module_versions` | One record per version of a module |
| `xlsform_module_version_locale` | Which locales/languages this version has translations for |
| `selected_xlsform_module_versions` | Which version each Team's Xlsform uses, with an `order` column |

**`xlsform_module_versions` columns:**
- `xlsform_module_id` — parent module (cascade delete)
- `owner_id` → `teams` table (null on team delete) — for team-owned custom versions
- `name` — human-readable label (unique per module)
- `is_default` — marks the "Global" version auto-created when a module is born
- `processing` — lock flag set during async import pipeline
- `country_id` — added January 2025 (⚠️ no FK constraint in the migration — may be intentional for flexibility, or an oversight)

---

## Model Hierarchy

```
XlsformTemplate
  └── XlsformModule  (has can_be_extended / can_be_replaced / row_names / default_order)
        └── XlsformModuleVersion  [implements HasMedia]
              ├── SurveyRow           (ordered by row_number)
              ├── ChoiceList
              │     └── ChoiceListEntry
              ├── Locale (many:many via xlsform_module_version_locale pivot)
              └── Xlsform (many:many via selected_xlsform_module_versions pivot)
```

`XlsformTemplate.locales` is a computed intersection: only locales present in **every** default module version appear as available on the template.

---

## Auto-creation of Default Version

[`XlsformModule.php:36-44`](packages/filament-odk-link/src/Models/OdkLink/XlsformModule.php#L36-L44) — the `booted()` hook fires `firstOrCreate` every time a module is created:

```php
$module->xlsformModuleVersions()->firstOrCreate([
    'name' => 'Global ' . $module->name,
    'is_default' => true,
]);
```

So there is always exactly one default version, hard-named `"Global {module_name}"`.

---

## Filament Resources

### Package-level (`OdkAdmin` panel) — `XlsformModuleVersionResource`
[`packages/filament-odk-link/src/Filament/OdkAdmin/Resources/XlsformModuleVersionResource.php`](packages/filament-odk-link/src/Filament/OdkAdmin/Resources/XlsformModuleVersionResource.php)

- Single `ManageRecords` page (no separate Create/Edit routes)
- Form: module selector (shows `"{template title} - {module name}"`), version name, **file upload only shown when `is_default = false`** (default versions get their data from the template file, not standalone upload)
- Table: template name, module name, is_default boolean icon, version name, `survey_rows_count` (computed via `counts()`)
- No `country_id` field — the package resource predates the January 2025 migration

### App-level (Admin panel) — `DietDiversityModuleVersionResource`
[`app/Filament/Admin/Resources/DietDiversityModuleVersionResource.php`](app/Filament/Admin/Resources/DietDiversityModuleVersionResource.php)

- **Uses the same `XlsformModuleVersion` model** but scopes the query to `xlsform_modules.name = 'diet_diversity'` via `getEloquentQuery()`
- Form: `Hidden` module_id hardcoded to `diet_diversity`, country selector (with `afterStateUpdated` that auto-fills `name` as `"diet_diversity {country_name}"`), file upload always required
- Standard List/Create/Edit page split (not ManageRecords)
- This pattern allows the Admin to add **country-specific diet diversity versions** that Teams can then select in the App Panel

### Package-level — `XlsformModuleRelationManager`
[`packages/filament-odk-link/src/Filament/OdkAdmin/Resources/XlsformTemplateResource/RelationManagers/XlsformModuleRelationManager.php`](packages/filament-odk-link/src/Filament/OdkAdmin/Resources/XlsformTemplateResource/RelationManagers/XlsformModuleRelationManager.php)

- Nested inside `XlsformTemplateResource`
- Columns: label, # survey rows (default version), # choice lists (default version), available locales
- Create/Edit/Delete actions inline

---

## Import Pipeline

Triggered by **Spatie MediaLibrary's `MediaHasBeenAddedEvent`**, handled by [`HandleXlsformTemplateAdded`](packages/filament-odk-link/src/Listeners/HandleXlsformTemplateAdded.php):

### Phase 1 — Synchronous module discovery (template uploads only)

`XlsformModuleImport` parses the `survey` sheet, groups rows by `module` column:
- Rows without a module name get auto-assigned to `"{template_title} - Unspecified Module N"` (new module per contiguous unnamed block)
- Reads `localisable_module` column: `"extend"` → `can_be_extended = true`, `"replace"` → `can_be_replaced = true`
- `updateOrCreate` on `XlsformModule`, which auto-fires the `booted()` hook to create the default version

### Phase 2 — Queued job chain (template and individual version uploads)

```
XlsformTemplateChoiceListImport (queued)
XlsformTemplateWorkbookImport (queued)
  └── chain:
       PrepareSurveyRowPaths
       FinishSurveyRowImport
       FinishChoiceListEntryImport
       LinkModuleVersionToLocales      ← locales sync
       ImportAllLanguageStrings
       FinishXlsformTemplateImport     ← sets processing = false
```

The `updated_during_import` flag on `SurveyRow` and `ChoiceListEntry` is the deletion mechanism: anything not touched during the import gets destroyed in `afterImport()` — this is how re-uploading a file cleanly replaces old content.

### Row-to-module routing

[`GetsModuleNamesPerRow` trait](packages/filament-odk-link/src/Imports/XlsformTemplate/GetsModuleNamesPerRow.php) routes each survey row to the correct module version:
1. If the model is already a `XlsformModuleVersion` (standalone upload), use it directly
2. Otherwise match the `module` column value to a module name
3. Fallback: match via `row_names` JSON (`"type_name"` combos stored on the module at import time)

### `LinkModuleVersionToLocales` job

[`packages/filament-odk-link/src/Jobs/LinkModuleVersionToLocales.php`](packages/filament-odk-link/src/Jobs/LinkModuleVersionToLocales.php)

- Parses column headers to detect languages (`label::language`, `label::fr`, etc.)
- `syncWithPivotValues(..., detaching: false)` — adds new locales without removing existing ones
- Any locale NOT updated in this import gets `needs_update = true` (flagging stale translations)
- Works for both a single `XlsformModuleVersion` or an entire `XlsformTemplate` (all default versions)
- ⚠️ **Known n+1 issue** noted in a TODO comment — each locale pivot is updated individually

### Upsert key for survey rows

[`XlsformTemplateSurveyImport`](packages/filament-odk-link/src/Imports/XlsformTemplate/XlsformTemplateSurveyImport.php) uses:
```php
public function uniqueBy(): array
{
    return ['xlsform_module_version_id', 'name', 'type'];
}
```
Re-uploading the same file updates existing rows rather than duplicating them.

---

## Team Selection Mechanism

### Xlsform ↔ Module Version wiring

[`Xlsform::syncWithTemplate()`](packages/filament-odk-link/src/Models/OdkLink/Xlsform.php) auto-attaches the **default** version for any module missing from a form — so new modules added to a template are automatically picked up by existing forms.

Teams can swap to a non-default version. For diet diversity this is handled in [`Team::booted()`](app/Models/Team.php#L96-L125):

When `diet_diversity_module_version_id` changes on a Team:
1. Finds all forms with a `diet_diversity` module version
2. Detaches the old version, attaches the new one **at the same `order` position**
3. Sets `draft_needs_update = true` to trigger redeployment

### App Panel UI

[`DietDiversity` place adaptation page](app/Filament/App/Pages/PlaceAdaptations/DietDiversity.php):
- If a team has no `diet_diversity_module_version_id`, auto-selects based on `team.country_id`
- Select shows only non-default (country-specific) versions
- Preview table of questions in the selected version
- Permission gated: `view adapt diet quality module` / `maintain adapt diet quality module`

---

## Custom Module Versions (LISP)

[`CustomModuleVersions` Livewire component](app/Livewire/Lisp/CustomModuleVersions.php):
- Each unmatched `LocalIndicator` owns its own `XlsformModuleVersion` (via `owner_id`)
- Teams download a template Excel, fill in questions, upload it
- A single file upload is processed against **all** unmatched indicator versions at once via `HandleXlsformTemplateAdded::processXlsformTemplate()`

[`WithXlsformModuleVersionQuestionEditing` trait](app/Filament/Shared/WithXlsformModuleVersionQuestionEditing.php) — reusable Filament trait for in-UI question editing on any module version:
- Add/Edit/Delete/Reorder questions via modal forms
- Supported types: `select_one`, `select_multiple`, `decimal`, `integer`, `text`
- Nested repeaters for language strings (label + hint per locale)
- Nested fieldset for choice lists and their entries
- Editing disabled for file-imported questions (`path != null`)
- Delete cascades to the associated `ChoiceList`

---

## Export

[`XlsformSurveyExport`](packages/filament-odk-link/src/Exports/XlsformExport/XlsformSurveyExport.php) assembles the final form by querying `SurveyRow` via a join through the `selected_xlsform_module_versions` pivot, ordering by:

1. `selected_xlsform_module_versions.order` — module position in form
2. `xlsform_module_version_id` — tiebreak
3. `row_number` — question position within module

---

## Authorization

Two Spatie-permission policies with `view`/`maintain` verbs:

| Policy | Permissions |
|---|---|
| `XlsformModulePolicy` | `view xlsform modules` / `maintain xlsform modules` |
| `XlsformModuleVersionPolicy` | `view xlsform module versions` / `maintain xlsform module versions` |

The `XlsformModuleVersionPolicy` explicitly covers both the package `XlsformModuleVersionResource` and the app-level `DietDiversityModuleVersionResource` (same model, noted in a comment at the top of the policy).

---

## Notable Issues / Open TODOs

| Location | Issue |
|---|---|
| [`XlsformModuleVersion.php:32`](packages/filament-odk-link/src/Models/OdkLink/XlsformModuleVersion.php#L32) | TODO: implement `WithXlsformDrafts` for pyxform validation and draft testing of individual module versions |
| [`LinkModuleVersionToLocales.php:68`](packages/filament-odk-link/src/Jobs/LinkModuleVersionToLocales.php#L68) | TODO: n+1 queries on locale pivot updates — each locale is updated with a separate query |
| [`XlsformTemplateSurveyImport.php:72-80`](packages/filament-odk-link/src/Imports/XlsformTemplate/XlsformTemplateSurveyImport.php#L72-L80) | Commented-out code for prefixing survey row names with team name to ensure uniqueness across teams in custom modules |
| [`add_country_id` migration](database/migrations/2025_01_09_131155_add_country_id_to_xlsform_module_versions_table.php) | No FK constraint on `country_id` — model has `BelongsTo(Country::class)` but migration lacks `->constrained()` |
| [`XlsformModuleVersionWasImported.php:11`](packages/filament-odk-link/src/Events/XlsformModuleVersionWasImported.php#L11) | Event carries `$xlsformModuleId` (not a version ID) despite being named `*VersionWasImported` — misleading |
| [`XlsformModuleVersionResource.php`](packages/filament-odk-link/src/Filament/OdkAdmin/Resources/XlsformModuleVersionResource.php) | No `country_id` field in the package resource form — `country_id` only exists in the app-level `DietDiversityModuleVersionResource` |
| [`XlsformTemplate.php:219`](packages/filament-odk-link/src/Models/OdkLink/XlsformTemplate.php#L219) | TODO: merge datasets + choice lists implementation (currently parallel systems) |
