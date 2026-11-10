# Plan: Stop "Replace XLSForm" from deleting team-owned module content

**Status: Completed** — implemented on 2026-11-09 (designs 0 to 3, 5, 6 and 7; design 4 stays a nice to have), deployed to staging and R3 run there on 2026-11-10. See [the change log](../change-logs/replace-xlsform-keeps-team-versions.md). Verified: local replay with the fix (all three drafts accepted by ODK Central, team versions kept, no wrong paths), R3 on a real damaged local database and on a copy of the staging database, and on staging itself (check SQL clean, Initial Pilot and a single-form Publish for the Guatemala - ARGIDIVI team created and published all three forms, which open in Enketo). Merged: host app PR #75 into `dev`; submodule PR #162 into `staging-hotfix-20261026` (not `dev`, which has been restructured). Not yet run on staging: a Replace XLSForm with the fix in place (the local replay covers it). There is no production environment yet. History: revision 3 found the real root cause (the post-import cleanup deletes team-owned versions); revision 4 added the second-order effects on other import steps; revision 5 added the second, independent cause (modules added by the new file never reach existing team forms, Design 7). The file name still says "prune-stale-rows" because the plan was first saved under that name.

The fix lives in the `packages/filament-odk-link` submodule (import classes), with a repair step in this repo. Implementing it means a commit in the submodule plus a submodule pointer bump here. Dan commits manually.

## Terms used in this plan

| Term | Meaning (class / table) |
|---|---|
| **Template** | An `XlsformTemplate` uploaded by a Super Admin. When marked "available", the system creates one team form per team. |
| **Team form** | The `Xlsform` created for one particular team from a template (`xlsforms.owner_id` = the team). It stores no xlsx; it stores which module versions the team selected (`selected_xlsform_module_versions`) and rebuilds the xlsx from the database in `generateXlsfile()`. |
| **Module** | A named group of questions inside a template (`xlsform_modules`), from the `module` column of the survey sheet. A group (`begin group` ... `end group`) can start in one module and end in another. |
| **Global (default) version** | `xlsform_module_versions.is_default = true`, one per module. Survey rows and choice lists come from the template file. Shared by every team. |
| **Local version** | A team-owned version (`owner_id` set, `is_default = false`) built by the host app from the team's own data: "Local locations" (`LocationsModuleBuilder`) and "Local farm info" (`FarmInfoModuleBuilder`). `Xlsform::localiseModules()` swaps it in for the global version. |
| **Cloned version** | A team-owned snapshot of a global version made by `XlsformModuleVersion::cloneForOwner()`. Only the Place Adaptations pages `InformedConsent` (clones the module that contains `enum_intro`, i.e. `introduction`) and `HddsHints` call it, the first time a team edits them. The team form is switched to the clone. |

## Problem

After an admin uses **Replace XLSForm** on an existing template, creating a draft for the team forms generated from that template fails on ODK Central. Alex's screenshot shows:

| Form | ODK error |
|---|---|
| Global Indicators | `[row : 521] Unmatched 'end_group'. No matching 'begin_group' was found for the name 'end group_567'.` |
| Women's Form | `[row : 132] Unmatched 'end_group'. No matching 'begin_group' was found for the name 'womens_section'.` |
| Farmer Registration | `[row : 7] ... the 'relevant' value is invalid ... Could not find the name 'participation'.` |

It happens from Initial Pilot and from Teams > Xlsforms > Publish (both call `generateXlsfile()`). It does not happen for the template's own draft (that uploads the original xlsx), and not for templates created fresh from the same file (the "1104" templates), which have no team-owned versions yet.

### What the data shows

In the Women's template (local DB, 2026-11-09), `begin group womens_section` is a row of the `introduction` module, `participation` is also in `introduction`, and `end group womens_section` is alone in the module `end_group_womens_form`. So if the `introduction` rows of a team form are missing, the generated form has exactly the three errors above: an `end_group` whose `begin_group` is gone, and a `relevant` that refers to a missing question. `introduction` is the module that `InformedConsent` clones for a team. `end group_567` is an unnamed `end group` of the same kind.

### Root cause

The cleanup that runs at the end of every template import is not limited to the template's global versions. `XlsformTemplate::surveyRows()`, `choiceListEntries()`, `surveyLanguageStrings()` and `choiceListEntryLanguageStrings()` are has-many-deep relations through **all** module versions of the template, including team Local and cloned versions. Rows of those team versions are never touched by the file import, so they always look "not updated during import" (`updated_during_import = false`) and get deleted.

| Where | What it does wrong |
|---|---|
| [XlsformTemplateWorkbookImport.php](../../packages/filament-odk-link/src/Imports/XlsformTemplate/XlsformTemplateWorkbookImport.php) `afterImport()` | Deletes every survey row of the template with the flag `false`, in all versions, through `SurveyRow::destroy()`. |
| same method | Deletes `owner_id IS NULL` choice entries of all versions with the flag `false`. |
| same method, last line | `ChoiceList::has('choiceListEntries', '=', 0)->delete()` has no template or version scope at all: it deletes every empty choice list in the whole database. |
| [XlsformTemplateLanguageStringImport.php](../../packages/filament-odk-link/src/Imports/XlsformTemplate/XlsformTemplateLanguageStringImport.php) `afterImport()` | Deletes default-locale language strings with the flag `false` through the same all-version relations. Found by reading the code; the tests below must prove it. |

The Replace flow then leaves the team forms in this state:

- Cloned `introduction` / HDDS versions have 0 survey rows. The team form still selects them, so the module's questions silently vanish from the generated file (`begin group womens_section`, `participation`, ...). ODK rejects the file.
- "Local locations" / "Local farm info" versions have 0 rows. `Team::localiseXlsforms()` only rebuilds them when `has_updated_locations` is true, so they stay empty, and `Xlsform::localiseModules()` swaps the empty version in for the global one. This is the already-documented [empty Local farm info issue](../issues/2026-10-28-empty-local-farm-info-module-version.md) (`entity_id` = `${id}` cannot be found), whose cause was recorded as "not known". The log entries of 2026-10-28 to 10-30 are the same mechanism.

This also corrects the first two drafts: rows removed from the file are **already** pruned correctly for global versions; no new pruning logic is needed.

### How this was verified

Throw-away Pest tests (deleted afterwards, nothing is left in the repo) ran the real import twice (v1, then a v2 with a question removed, two inserted, a group renamed and unnamed `end group`/`end repeat` rows shifted) on an in-memory database:

| Check | Result |
|---|---|
| Global rows after the v2 import | Match v2 exactly: removed question gone, renamed group replaced, shifted unnamed `end ...` rows renamed with no leftovers. |
| A team "Local" version with 1 survey row | **Deleted** by the v2 import (1 → 0). |
| A team clone of the `drinks` module (12 rows, 9 survey strings, 2 lists, 10 entries) | After v2: survey rows **12 → 0**, survey strings **9 → 0**. Choice lists and entries survived (10 and 10), because the choices import also writes into same-named lists of other versions. |
| An empty choice list that belongs to a different template | **Deleted** by the v2 import. |

Still to be confirmed on staging, read-only, before and after the fix: the SQL in "Pre-implementation check".

## Second cause: modules added by the new file never reach existing team forms

Found in the local reproduction (2026-11-09). The new Farmer Registration file adds an `introduction` module that the old file did not have (module 33, `default_order` 2, default version 43 holding `participation`). None of the three existing team forms of that template select it.

| Fact | Where it comes from |
|---|---|
| Replace calls `markAllAsNotCurrent()`, a bulk `update(['has_latest_template' => false])`, which fires no model events. | [XlsformTemplate.php](../../packages/filament-odk-link/src/Models/OdkLink/XlsformTemplate.php) `markAllAsNotCurrent()` |
| `Xlsform::syncWithTemplate()` is the only code that attaches missing modules. It runs from the `saved` hook (only on a non-quiet save while `has_latest_template` is false) and from `setup()` (new forms). | [Xlsform.php](../../packages/filament-odk-link/src/Models/OdkLink/Xlsform.php) `booted()`, `setup()` |
| Initial Pilot (`deployDraftForms`), single-form Publish and `generateXlsfile()` (it uses `updateQuietly`) never call it. | same files, `Team::deployDraftForms()` |
| Result: the generated file has no `participation`, and the first row of the next module that says `relevant = ${participation}` (row 7) is rejected. This matches Alex's third error. | local query: forms 1, 2, 3 lack module 33 |

A second problem hides behind it. Even when `syncWithTemplate()` runs, it attaches the new module with `order = default_order`, but the forms keep the pivot orders from the old file (metadata 1, locations 2, new farm info 3). The new module's `default_order` is 2, which ties with locations; the export sorts ties by version id, so `introduction` (43) would land **after** locations (5) instead of before it. Team forms cannot simply be renumbered from `default_order`, because a team can reorder its modules (Lisp > Optional modules, `OptionalModules::updateOrder()`).

## Second-order effects: what else breaks once team versions survive

Today the cleanup wipes team versions *before* the later import steps run, which hides a second problem: several of those steps also read the template-wide (all versions) relations. If only the cleanup is fixed, they start to see the surviving team versions.

| Step | What it does with all versions | Effect once team versions survive | Evidence |
|---|---|---|---|
| `PrepareSurveyRowPaths` (runs right after the workbook import) | Loads `$template->surveyRows()` ordered only by `row_number` and walks one running path through them. | Global and cloned rows with the same row numbers are interleaved, so the running path is wrong. The `path` of **global** rows is overwritten with wrong values. `path` is read by submission processing (`OdkSubmissionService`) and by the review structure in `XlsformTemplate`, and rows with a path cannot be edited in the question editor. | **Confirmed by a throw-away test** (deleted): template from v1.xlsx, one module cloned for a team, `PrepareSurveyRowPaths` run: 5 of 25 global rows changed, e.g. `/drinks_rpt_grp/drink_name` became `/drinks_rpt_grp/drinks_rpt_grp/drink_name` and `/drinks_rpt_grp/` became `/`. |
| `XlsformTemplateLanguageStringImport::model()` | Finds the row or entry for each language string with `$template->surveyRows->filter(name...)->first()` / `choiceListEntries`. | If a clone or Local row has the same name, `first()` may return it instead of the global row. The string is then written to the wrong row, the global row's string stays `updated_during_import = false` and is deleted by the cleanup, and the global label disappears. | Not reproduced: in the test the global row came first (lower id), but the query has no `ORDER BY`, so it is not guaranteed. |
| `XlsformTemplateChoicesImport::collection()` | Writes each choice row into **every** choice list of the template with that name. | New and changed choices are also upserted into team clones and Local lists, overwriting `properties` of same-named entries. This makes a clone only half a snapshot (choices updated, questions not). | By code reading; the earlier experiment showed clone choice lists were refilled. |

The fix therefore has to be one rule, applied everywhere: **the file import only reads and writes global versions.**

## What "Replace XLSForm" updates after the fix

| Content | After Replace |
|---|---|
| Global versions: questions, choices, translations from the file | Match the new file exactly (already true). |
| New modules in the file | **Not attached today** (see "Second cause"). After Design 7 they are attached at their position in the file. |
| The template's own ODK draft, entity lists, settings, required media | Updated (already works). |
| Local versions (locations, farm info) | **Kept** as they are (today they are emptied). They come from team data, not from the file. |
| Cloned versions (Informed Consent, HDDS) | **Kept** as the team's own snapshot (today they are emptied). They are not updated with the new file; a team that has already customised these modules keeps its copy. |

Known residual risk: a cloned snapshot is an old copy. If the new file renames or removes a question in a cloned module, another module's `relevant` or `calculation` may refer to a name that no longer exists in that team's form, and ODK rejects it with a message that names the missing question. Syncing clones with the new file while keeping the team's edited labels and hints is a separate feature (see "Out of scope"). Design 5 adds a log line so the affected teams are visible.

## Decisions from Dan (2026-11-09) and their status

1. Team-owned content is left as it is: this is now the core of the fix. The earlier "only warn about modules still selected by a team" decision no longer applies, because the plan no longer deletes modules.
2. Plan lives in the host repo `docs/plans`.
3. "No repair command" was decided when the cause was believed to be stale rows that a re-upload would clear. With the real cause, re-uploading the file does **not** restore emptied Local or cloned versions. Dan confirmed on 2026-11-09 that the repair command (Design 6) should be added, and that Design 4 is "nice to have" for later.

## Design

### 0. One set of "import scope" relations (the mechanism for designs 1, 2 and 2b)

Add five methods that return the rows the file import is allowed to touch, on both models:

| Method | On `XlsformTemplate` | On `XlsformModuleVersion` |
|---|---|---|
| `surveyRowsForImport()`, `choiceListsForImport()`, `choiceListEntriesForImport()`, `surveyLanguageStringsForImport()`, `choiceListEntryLanguageStringsForImport()` | The existing has-many-deep relation plus `where('xlsform_module_versions.is_default', true)` (same condition as `XlsformModule::defaultSurveyRows()`). | The existing relation unchanged (the model is already one version, so a team's custom module upload keeps working). |

The import classes and jobs then call these instead of `surveyRows()` etc. This avoids repeating the condition in eight places and makes the rule hard to forget. The old relations stay untouched for every other use.

### 1. Limit the survey row, choice entry and empty-list cleanup to global versions

In `XlsformTemplateWorkbookImport::afterImport()`, use `surveyRowsForImport()` and `choiceListEntriesForImport()` instead of `surveyRows()` and `choiceListEntries()`.

Replace the last line with a scoped version: only choice lists that belong to this model (global versions of the template, or the version itself) and have no entries, deleted through Eloquent so `ChoiceList::deleting` still removes the entries' language strings. Nothing outside the imported template may be touched.

Keep deleting through models (`SurveyRow::destroy`, `ChoiceListEntry::destroy`), as the code comment says: the `deleting` hooks remove language strings, which have no foreign key.

### 2. Limit the language string cleanup to global versions

In `XlsformTemplateLanguageStringImport::afterImport()`, use `surveyLanguageStringsForImport()` and `choiceListEntryLanguageStringsForImport()`. The resets in `FinishLanguageStringImport`, `FinishSurveyRowImport` and `FinishChoiceListEntryImport` also run over all versions; that only flips a flag on team rows, so it is harmless and is left alone.

### 2b. Limit the other import steps to global versions

- `XlsformTemplateLanguageStringImport::model()`: look up rows and entries through `surveyRowsForImport()` / `choiceListEntriesForImport()` so a string can never be attached to a clone's row.
- `PrepareSurveyRowPaths::handle()`: use `surveyRowsForImport()`. The group structure of a template can span modules, so all **global** versions of the template must still be walked together in `row_number` order; only team versions are left out. Paths of cloned and Local rows are not recomputed (they were copied or built with their own paths).
- `XlsformTemplateChoicesImport::collection()`: use `choiceListsForImport()`, so new choices are written to global lists only. A clone then stays a complete snapshot of choices and questions.
- `FinishChoiceListEntryImport` (the `$localisableChoiceLists` query): use `choiceListsForImport()` for the same reason. Its flag reset stays as it is.
- `AddMissingChoiceListStrings` copies labels between same-named lists of one template. Left alone: it only fills missing labels.

### 3. Flag the team forms for republish when the import is complete

In `FinishXlsformTemplateImport::handle()` (template case): `$this->model->xlsforms()->update(['draft_needs_update' => true])`. Today Replace only sets `has_latest_template = false` ([XlsformTemplate.php](../../packages/filament-odk-link/src/Models/OdkLink/XlsformTemplate.php) `markAllAsNotCurrent()`); the upsert import does not fire `SurveyRow::saved`, so team forms are never told to rebuild their draft. The "updates available to publish" label in `XlsformsTableView` reads `draft_needs_update`. It runs at the end of the chain so a form is not flagged before the new content exists. Nothing is published automatically.

### 4. Hardening: never swap in an empty Local version

In `Xlsform::localiseModules()`, skip the swap and log a warning when the Local version has no survey rows. This is direction 1 of the existing empty Local farm info issue. It protects forms from any other way a Local version can end up empty. **Nice to have** (Dan, 2026-11-09): not part of the first implementation, may be added later. It hides the symptom instead of repairing the data.

### 5. Make team-owned versions visible after a Replace

Also in `FinishXlsformTemplateImport` (template case), log one line listing the non-default versions of this template's modules (owner, name, module, survey row count). This tells an admin which teams hold their own copy and shows an emptied version at once.

### 6. Repair existing damage (confirmed by Dan, 2026-11-09)

Every environment on which a template was replaced after teams had Local or cloned versions may already hold emptied versions (staging: yes, per Alex's report; production: unknown, check first). Re-uploading the template does not bring them back. Proposed: a third repair, R3, in the existing `odk:repair-form-deploy-issues` command (same `--apply` / dry-run pattern as R1 and R2), selected with `--only=versions`:

- **Local versions** ("Local locations", "Local farm info") with 0 rows: rebuild them with `LocationsModuleBuilder::populate()` and `FarmInfoModuleBuilder::populate()` for that team. Both are idempotent (`updateOrCreate`).
- **Cloned versions** (team-owned, not named "Local ...") with 0 survey rows while the default version of the same module has rows: switch the team's forms back to the global version at the same `order`, then delete the emptied clone. The Informed Consent / HDDS page clones the global version again the next time the team opens it. The team's earlier label and hint edits on those modules are gone for good (the rows and their strings were deleted); only a database backup can bring them back.
- **Forms missing a module of their template** (the Design 7 case, already happened locally and on staging): attach it with the same insertion logic as Design 7.
- Mark the affected team forms `draft_needs_update = true`.

Dry run first. It lists team, form, version and what would be done.

Because a repaired or rebuilt copy is a new snapshot of the current global version, the global `path` values and everything else are untouched.

### 7. Attach new modules to existing team forms, in the right position

When the import has finished (`FinishXlsformTemplateImport`, template case), for each team form of the template: reload its selected versions and call `syncWithTemplate()`, then flag the form (Design 3). This covers the first half of the problem.

For the ordering half, change how `syncWithTemplate()` attaches a missing module **when the form already has other versions selected** (a form with no selection, as in `setup()`, keeps today's behaviour):

1. Find the form's selected module with the highest `default_order` below the new module's (its predecessor). Its "block" includes its Local version, which sits right after it.
2. Give the new module the order right after that block and shift every selected version with a greater order by +1. The relative order of everything else is unchanged, so a team's custom order survives.
3. No predecessor: insert first.

For the local data: metadata 1, introduction 2, locations 3, new farm info 4, which is the order in the new file.

Modules removed from the file are not touched (Dan's earlier decision: only log).

## Pre-implementation check (staging and production, read-only)

All queries only read. Run them on staging and on production (MySQL).

**A. Team-owned versions that lost their rows** (the damage):

```sql
SELECT v.id, v.name, v.owner_id, m.xlsform_template_id AS template_id, m.name AS module,
       (SELECT COUNT(*) FROM survey_rows s WHERE s.xlsform_module_version_id = v.id) AS own_rows,
       (SELECT COUNT(*) FROM survey_rows s JOIN xlsform_module_versions d ON d.id = s.xlsform_module_version_id
         WHERE d.xlsform_module_id = v.xlsform_module_id AND d.is_default = 1) AS global_rows,
       (SELECT COUNT(*) FROM selected_xlsform_module_versions x WHERE x.xlsform_module_version_id = v.id) AS forms_using_it
FROM xlsform_module_versions v
JOIN xlsform_modules m ON m.id = v.xlsform_module_id
WHERE v.is_default = 0 AND v.owner_id IS NOT NULL
HAVING own_rows = 0 AND global_rows > 0;
```

Expected on staging: the cloned `introduction` and HDDS versions and the Local locations / farm info versions of the affected teams, matching the three errors. If production returns no rows, nothing has been damaged there yet: do not Replace any template on production until the fix is deployed.

**B. Which team forms are affected** (use the version ids from A):

```sql
SELECT f.id AS form_id, f.title, f.owner_id AS team_id, f.xlsform_template_id, f.draft_needs_update,
       v.id AS version_id, v.name AS version_name, x.`order`
FROM selected_xlsform_module_versions x
JOIN xlsforms f ON f.id = x.xlsform_id
JOIN xlsform_module_versions v ON v.id = x.xlsform_module_version_id
WHERE v.is_default = 0 AND v.owner_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM survey_rows s WHERE s.xlsform_module_version_id = v.id)
ORDER BY f.xlsform_template_id, f.owner_id, x.`order`;
```

**C. Overview of every team-owned version** (how many clones and Local versions exist, to judge the size of the stale-clone risk):

```sql
SELECT m.xlsform_template_id AS template_id, m.name AS module, v.owner_id AS team_id, v.id AS version_id, v.name,
       (SELECT COUNT(*) FROM survey_rows s WHERE s.xlsform_module_version_id = v.id) AS rows_now,
       (SELECT COUNT(*) FROM survey_rows s JOIN xlsform_module_versions d ON d.id = s.xlsform_module_version_id
         WHERE d.xlsform_module_id = v.xlsform_module_id AND d.is_default = 1) AS global_rows
FROM xlsform_module_versions v
JOIN xlsform_modules m ON m.id = v.xlsform_module_id
WHERE v.is_default = 0 AND v.owner_id IS NOT NULL
ORDER BY m.xlsform_template_id, m.name, v.owner_id;
```

**D. Choice lists with no entries** (the unscoped delete removes these across all templates, so this shows how many are at risk or already gone):

```sql
SELECT cl.id, cl.list_name, cl.xlsform_module_version_id
FROM choice_lists cl
WHERE NOT EXISTS (SELECT 1 FROM choice_list_entries e WHERE e.choice_list_id = cl.id);
```

**E. Wrong paths on global rows** (only relevant after a fix that missed `PrepareSurveyRowPaths`; run it after deploying to confirm no global row has a doubled segment; it is a heuristic, a legitimately repeated name would also match, so judge the rows by eye):

```sql
SELECT s.id, s.name, s.path
FROM survey_rows s
JOIN xlsform_module_versions v ON v.id = s.xlsform_module_version_id
WHERE v.is_default = 1 AND s.path REGEXP '^(/[^/]+)*/([^/]+)/\\2/';
```

**F. Team forms missing a module of their template** (Design 7; result seen locally: forms 1, 2, 3 miss `introduction`):

```sql
SELECT f.id AS form_id, f.owner_id AS team_id, f.xlsform_template_id, m.id AS module_id, m.name, m.default_order
FROM xlsforms f
JOIN xlsform_modules m ON m.xlsform_template_id = f.xlsform_template_id
WHERE NOT EXISTS (
  SELECT 1 FROM selected_xlsform_module_versions x
  JOIN xlsform_module_versions v ON v.id = x.xlsform_module_version_id
  WHERE x.xlsform_id = f.id AND v.xlsform_module_id = m.id)
ORDER BY f.id, m.default_order;
```

Modules that were removed from the file also show up here only if the form never had them; a module that is still selected does not.

## Tests (Pest)

Package tests in `tests/Unit` (helpers `makeXlsformTemplate()`, `addModuleVersion()` in `tests/Pest.php`) and a host feature test in the style of `tests/Feature/XlsformTemplateImportTest.php` (real import, sync queue), using two fixture files v1/v2 in `tests/assets`:

1. Global rows removed from the file are deleted; renamed groups and shifted unnamed `end group` / `end repeat` rows leave no leftovers (protects the behaviour that already works).
2. A team Local version keeps its survey rows (and their language strings) after a Replace.
3. A cloned version (`cloneForOwner()`) keeps its survey rows and survey language strings after a Replace.
4. Team-owned choice entries (`owner_id` set) are kept; global entries removed from the file are deleted.
5. An empty choice list in a global version is deleted; an empty choice list of another template or of a team version is **not**.
6. A module version upload (`XlsformModuleVersion` model) still prunes its own rows.
6a. With a cloned version present, a Replace leaves the `path` of every global row identical to a replace without a clone (the confirmed regression), and `PrepareSurveyRowPaths` leaves the clone's paths untouched.
6b. With a clone whose rows share names with the global rows, language strings from the new file go to the global rows only, and the global labels are updated.
6c. The choices import writes to global lists only; a clone's choice entries and `properties` are unchanged after a Replace.
7. `FinishXlsformTemplateImport` sets `draft_needs_update = true` on the template's team forms and logs the team-owned versions.
8. If Design 4 is accepted: `localiseModules()` does not swap in an empty Local version and logs a warning.
9. If Design 6 is accepted: R3 dry run changes nothing and lists the damaged versions; `--apply` rebuilds the Local versions, re-points forms from empty clones to the global version, deletes the clones, and flags the forms.

10. After a Replace whose file adds a module, every team form of the template selects the new module's default version.
11. The new module is inserted at its file position: with forms ordered metadata 1, locations 2, farm info 3 and a new module with `default_order` 2, the result is metadata, new, locations, farm info; after a custom reorder the other versions keep their relative order; a Local version stays right after its module.
12. A form with no selected versions yet (`setup()`) is attached exactly as today.
13. If Design 6 is accepted: R3 attaches missing modules to existing forms the same way, and its dry run lists them.

Then run the full package suite (`composer test`), the host suite (`./vendor/bin/pest`), PHPStan and Pint.

## Local reproduction checklist (before any code change)

Goal: show the bug on the local database, keep a dump so the same state can be replayed after the fix. Expected results are from reading the code, not yet observed; write down any difference.

Query V (used in several steps; replace the template id):

```sql
SELECT v.id, v.name, v.owner_id, v.is_default,
       (SELECT COUNT(*) FROM survey_rows s WHERE s.xlsform_module_version_id = v.id) AS survey_rows
FROM xlsform_module_versions v JOIN xlsform_modules m ON m.id = v.xlsform_module_id
WHERE m.xlsform_template_id = :template_id ORDER BY v.is_default DESC, v.id;
```

| # | Where | Do | Expect |
|---|---|---|---|
| 1 | Terminal | Back up or rebuild the local DB with the seeders. | Clean start. |
| 2 | Admin Panel > Xlsform Templates | Create the templates from the **old** files; mark each "available". Wait for "Xlsform Template Imported". | Each template has modules; query V shows only `is_default = 1` versions with rows. |
| 3 | Admin Panel > Teams | Create or pick a team; make sure its team forms exist (Teams > Xlsforms tab). | One team form per available template. |
| 4 | App Panel `/app/{team}` | Add or import at least one location and one farm. | `teams.has_updated_locations = 1`. |
| 5 | App Panel > Place-based adaptations > Initial Pilot | Create the drafts once (needs ODK Central). If ODK is not reachable, skip steps 4 and 5; only the cloned version is reproduced, not the Local versions. | Drafts succeed. Query V now also shows "Local locations" and "Local farm info" versions with rows. |
| 6 | App Panel `/app/{team}/informed-consent` (from Place-based adaptations > "Adapt informed consent" > Update) | Open the page once; do not save. | Query V shows a new version `introduction - {team name}` with `owner_id` = the team and the same row count as the default `introduction`. |
| 7 | Terminal | Dump the DB (for example `mysqldump` / `sqlite` copy). | Restore point for the re-test after the fix. |
| 8 | Admin Panel > Xlsform Templates > template | Click **Replace XLSForm** with the **latest** file. Wait for "Xlsform Template Imported". | Notification arrives. |
| 9 | Terminal | Run query V again, then queries A, B and F from "Pre-implementation check". | Bug reproduced if: the team's `introduction - {team}` clone and the Local versions show `survey_rows = 0` while the default versions have rows (A, B), and a form of a template that gained a module lists it as missing (F). |
| 10 | App Panel > Initial Pilot (or Admin Panel > Teams > team > Xlsforms > Publish) | Create the drafts. | Bug reproduced if ODK rejects with the "Unmatched end_group" / `relevant` messages. Expected per template: Global Indicators and Women's Form fail on the emptied `introduction` clone; Farmer Registration fails on `participation` because its form lacks the new `introduction` module (second cause). |
| 11 | Terminal | Also run query E. | Rows here would mean `path` values are already wrong; not expected before the fix. |

Observed locally on 2026-11-09 (steps 1 to 9): query A listed 7 emptied team versions (Local locations x3, Local farm info x2, `introduction - P1 Test Team 1` x2); query B showed form 4 (Global Indicators) and form 7 (Women's Form) selecting the emptied clones; query F showed forms 1, 2, 3 (template 1) missing module 33 `introduction`. Step 10 (Initial Pilot, same day), read from `failed_jobs` and `laravel.log`:

| Form | Result |
|---|---|
| Farm Registration (template 1) | First attempt rejected by ODK: `[row : 7] ... 'relevant' value is invalid ...` (the second cause, `participation` missing). Second attempt **succeeded**: by then the form selected `introduction` (version 43) because a later non-quiet save of the form ran `syncWithTemplate()`, which is most likely the save made by `UpdateXlsformFile` after the file was built, i.e. too late for the first attempt. The new module was attached with order 2, the same as locations (the predicted tie), and ODK accepted the form. |
| Global Indicators | Rejected every time: `[row : 542] Unmatched 'end_group' ... 'end group_567'` (emptied `introduction` clone). |
| Women's Form | Rejected every time: `[row : 219] Unmatched 'end_group' ... 'womens_section'` (emptied `introduction` clone). |

The row numbers differ from Alex's (his data differs), the messages are the same. After the failures all forms have `processing = 0` and the queue is empty (7 rows in `failed_jobs`), so the forms are not stuck; the front end kept showing the loading state anyway because `failed()` clears the flag with `updateQuietly` (no event for the page). That is a separate UI problem (see "Out of scope"). Two web requests also died with `Allowed memory size of 134217728 bytes exhausted` (PHP-FPM limit 128M, a 47 MB query result) at 15:39 and 15:41; not analysed, not related to this bug.

After the fix is implemented: restore the dump from step 7, deploy the fix, repeat steps 8 to 11. Expected: the clone and Local versions keep their rows, query E returns nothing, drafts are accepted. If ODK still rejects a form, the message names a missing question: that is the stale-clone risk, and the team's clone differs from the new file.

## Manual verification

1. Local, **reproduce first (before any code change)**; the step-by-step version is in "Local reproduction checklist" above. In short: seed, create the templates from the old files, mark them available, create a team (it gets a team form per template), open the team's Informed Consent page once (this creates the cloned `introduction` version) and make sure the team has locations / farm data so the Local versions are built, then Replace with the latest files. Expected before the fix: query A returns the cloned and Local versions with 0 rows, and Initial Pilot / Publish fails with the errors above. Without the Informed Consent visit there is no clone and the error will not appear. Then repeat after the fix: the same team versions keep their rows, query E returns nothing, and the ODK draft is accepted.
2. Staging: run the check SQL, deploy, run R3 (dry run, then `--apply`) if accepted, Replace each of the three templates with the latest file, wait for the "Xlsform Template Imported" notification, then run Initial Pilot and a single-form Publish.
3. Read the log lines of Design 5.

## Out of scope

- Syncing cloned versions (Informed Consent, HDDS) with new questions of the global module while keeping the team's edited labels and hints (needs a merge design).
- Modules that disappear from the file: their rows are already removed, the empty module and its default version stay, and team forms keep an empty selection. Harmless; tidy up later if wanted.
- The Initial Pilot page keeps its loading state after a failed deployment, although the form's `processing` flag is already false (`DeployDraftXlsformToOdkCentral::failed()` uses `updateQuietly`, so nothing tells the page). Worth its own issue.
- Two web requests ran out of PHP-FPM memory (128M) during the local reproduction (a 47 MB query result at `MySqlConnection.php:53`). Find which page does it before blaming the import.
- Modules that the new file moves to a different position: existing team forms keep their order (it may be a team's own order). Only modules that are newly added are inserted at their file position.
- An admin action to reset all clones of one template back to the global version (R3 does this only for emptied clones). Useful if a stale snapshot makes ODK reject a form after the new file renamed or removed a question; the team's label and hint edits would be lost. Decide after the local reproduction shows how often that happens.
- Blocking team form generation while the template's `processing` flag is true.
- Updating `docs/issues/2026-10-28-empty-local-farm-info-module-version.md` with the cause found here (suggested once this plan is accepted).
