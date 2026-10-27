# Plan: Fix crop_list labels, module localisation, choice order (staging issues reported 2026-10-26)

**Status: In Progress** — Phase 1 (items 1-5) is implemented, committed and verified end to end on a clean local DB (see the [Phase 1 change log](../change-logs/2026-10-26-odk-form-deploy-fixes-phase-1.md)); a follow-up to item 5 (team entries ordered after global ones) is implemented in the working tree but not yet committed. Still to do: commit/deploy, the data repair and SQL steps under "Rollout order", and Phase 2 (item 6, explicit choice `row_number`).

All code changes are in the `packages/filament-odk-link` submodule (currently at `e048b4b`). Implementing them means a commit in the submodule plus a submodule pointer bump in this repo; staging needs both deployed, then `php artisan queue:restart` (the staging worker is a long-running `queue:work database --daemon` and keeps running old code until restarted).

## Background

Alex (team 3, "Guatemala - ARGIDIVI") reported on staging that:

1. The `crop_list` labels were "lost", and the Global Indicators form failed to deploy until he added at least one crop by hand.
2. Locations and farms he uploaded did not show up in the forms; the draft forms and the XLS downloaded from Central contain the placeholders (`loc_1_2`, `loc_1_3`, ...).
3. Choice lists are rebuilt in alphabetical order instead of template order.
4. Uploading translation files one by one left the locale stuck on "Processing" (diagnosis only, see "Findings without a fix in this plan").

The investigation used clones of the staging DB plus the three latest master xlsx files (`20261026 Latest ODK Forms`).

## Decisions (agreed with Dan)

- `can_be_replaced` is fixed with **option a + option c**: (a) turn the flag on for the five builder-backed modules by SQL now, and (c) change the template import so it stops overwriting the flags when the xlsx has no `localisable_module` column.
- Choice order: **option A first** (export in template order using entry id), **then option B** (explicit `row_number` column).
- The deploy order below matters (see "Rollout order").

## Problem 1: crop_list global entries have no labels

### Symptoms

- `failed_jobs` (14:09 and 14:15): `ODK Validate Errors ... <label> node for itemset doesn't exist! [instance(crop_list)/root/item/label]`.
- `choice_lists` has two `crop_list` rows: #28 (`crops` module version 13) and #53 (`crop_intensification` module version 15). Their 35 global entries (names 1-35) are `#28: 0/35 labelled`, `#53: 35/35 labelled`. No other list in the DB has unlabelled global entries.
- The master xlsx is fine: `crop_list` has 35 rows, each labelled in all four languages.

### Root cause (two defects that combine)

**A. `AddMissingChoiceListStrings` can pick the list itself as the source.**
After the label import, [AddMissingChoiceListStrings](../../packages/filament-odk-link/src/Jobs/AddMissingChoiceListStrings.php) copies labels from "the first same-named list that has any language strings" into same-named lists that have unlabelled entries. For `crop_list`, team-owned, already-labelled entries (added through the 10/19 crop_list sync feature) existed in #28 before the 10/26 re-upload, so #28 qualified as the source for itself. The job copied #28 onto #28 and did nothing. The label import itself only attached the 35 global labels to #53, so #28's global entries stayed unlabelled. Every other duplicated list (`who_family`, `yes_no`, ...) has no such pre-existing team labels, so the source resolved to the freshly imported copy and it worked.

**B. `XlsformChoicesExport` collapses duplicates with `max(id)`.**
[XlsformChoicesExport::query()](../../packages/filament-odk-link/src/Exports/XlsformExport/XlsformChoicesExport.php) groups by `(list_name, name, properties, cascade_filter)` and keeps `max(choice_list_entries.id)`. After the re-import the unlabelled #28 entries have the higher ids, so the exported `crop_list` rows carry no label at all. With no label in any language, pyxform emits an itemset without a `<label>` node and ODK Validate rejects the form. Alex's manual crop additions exist in both lists with labels, so the label column appeared and validation passed, but global crops 1-35 are still exported blank.

## Problem 2: forms do not use the team's "Local" modules

### Symptoms

- team 3 has populated `Local locations` (v#54-56) and `Local farm info` (v#57-58), and 179 farms exist on Central, but forms 7/8/9 still select `Global locations` / `Global farm info`. The global `loc1` choice list only holds placeholder names (`1,2,3,4`), which is what Alex sees.
- team 1 and team 2 forms select module versions that belong to a different template's module (e.g. Global Indicators form 2 selects v#38, the Farm Registration `locations` clone; Women's form 3 selects v#41, the Global Indicators `farm info` clone). Forms 5 and 6 contain two versions of the same module at the same `order`.

### Root causes

1. **Flag is off.** [Xlsform::localiseModules()](../../packages/filament-odk-link/src/Models/OdkLink/Xlsform.php) only acts on modules with `can_be_replaced = true`. In the staging DB the flag is `0` for every module: none of the three master xlsx files has a `localisable_module` column, and [XlsformModuleImport](../../packages/filament-odk-link/src/Imports/XlsformTemplate/XlsformModuleImport.php) overwrites `can_be_extended` / `can_be_replaced` with `false` on every upload (`updateOrCreate` with both flags), so any value set in the admin UI is lost on the next upload.
2. **Wrong lookup.** `localiseModules()` finds the team's local version with `owner_id` + `name = 'Local <module name>'` only. Module names such as `locations` and `farm info` repeat across templates (one `XlsformModule` row per template), so `->first()` returns the oldest match, usually another template's version. The builders ([LocationsModuleBuilder](../../app/Services/XlsformModules/LocationsModuleBuilder.php), [FarmInfoModuleBuilder](../../app/Services/XlsformModules/FarmInfoModuleBuilder.php)) correctly create one version per `xlsform_module_id`.

**Why the order matters:** turning the flag on while the lookup is still wrong makes it worse. For team 3, `first()` returns v#54 (module 3) for every template's `locations` module, so the Global Indicators and Women's forms would get a version belonging to a different module. `syncWithTemplate()` then sees no version for their own module and re-attaches the default, producing the duplicate-version state already seen in forms 5 and 6. The lookup fix (item 3) must be deployed **before** the flags are set.

## Problem 3: choices are exported in alphabetical order

[XlsformChoicesExport::query()](../../packages/filament-odk-link/src/Exports/XlsformExport/XlsformChoicesExport.php) ends with `orderBy('choice_lists.list_name')->orderBy('choice_list_entries.name')`. Template order is lost, including for numeric names (`reliability` is `5 > 4 > 3 > 2 > 1` in the template and is exported `1 > 2 > 3 > 4 > 5`). `rapport` is `easy > medium > difficult > very_difficult > no_answer` in the template (and in the DB, ids 1155-1159) but is exported `difficult, easy, medium, no_answer, very_difficult`. `choice_list_entries` has no order column; ids do follow template order because the import inserts in file order.

## Changes

### Phase 1

#### 1. `AddMissingChoiceListStrings`: never use the list itself as the source

- Resolve candidate source lists as every same-named list in the same template (or module version) **excluding the list being filled** (`id != $choiceList->id`), that has at least one language string.
- For each unlabelled entry, search the candidate lists for an entry with the same `name`, `properties`, `cascade_filter` **and `owner_id`**, and that has language strings; copy from the first one found. Adding `owner_id` stops a team-owned entry (e.g. team 2's crop `41`) from being matched against another team's entry of the same name.
- Keep the existing `updateOrCreate` copy and the "no match, skip" behaviour.

#### 2. `XlsformChoicesExport`: prefer a labelled entry as the group representative

- Change the `id` column of the grouped query from `max(id)` to "the highest id among entries that have language strings, falling back to `max(id)`". In SQL:
  `coalesce(max(case when exists (select 1 from language_strings where linked_entry_type = ? and linked_entry_id = choice_list_entries.id) then choice_list_entries.id end), max(choice_list_entries.id)) as id` with `ChoiceListEntry::class` as the binding.
- No change to grouping or ownership filtering. When a team-owned entry and a global entry share a name and properties, the one with labels wins instead of simply the newer one; today this only differs from `max(id)` when one side is unlabelled, which is the bug.

#### 3. `Xlsform::localiseModules()`: scope the local-version lookup to the module

- Add `->where('xlsform_module_id', $xlsformModule->id)` to the `XlsformModuleVersion` query.
- No change to the `can_be_replaced` filter, `syncWithTemplate()`, or the `can_be_extended` branch (which intentionally creates standalone `Local <name>` versions with no module id).

#### 4. `XlsformModuleImport`: do not overwrite the flags when the xlsx has no `localisable_module` column

- Detect whether the survey sheet has the `localisable_module` column (key present in the heading row, even if the cell is blank).
  - **Column present:** behave as today (the xlsx is authoritative: `extend` sets `can_be_extended`, `replace` sets `can_be_replaced`, blank sets both to `false`).
  - **Column absent:** leave `can_be_extended` / `can_be_replaced` untouched on existing modules; newly created modules get the column defaults (`false`).
- `label`, `default_order` and `row_names` keep being updated as today.

#### 5. `XlsformChoicesExport`: export in template order (option A)

- Replace `orderBy('choice_list_entries.name')` with an order by the group's lowest id (`orderByRaw('min(choice_list_entries.id)')`); keep `orderBy('choice_lists.list_name')` first. Entries are inserted in template order on import.
- **Follow-up found in the local end-to-end test:** the original assumption "team-added entries get later ids" does not hold once the template is re-uploaded, because the import recreates the global entries with new, higher ids. In the local test the three team crops (41-43), added before the re-upload, were exported **before** the 35 global crops. The order is therefore `list_name`, then global before team-owned (`min(case when owner_id is null then 0 else 1 end)`), then lowest id. An entry that overrides a global one with the same name and properties sorts with the global group.
- Known limit of option A: if the template author later moves an existing choice, the DB id order does not change. Phase 2 fixes that.

### Phase 2

#### 6. Explicit choice order (option B)

- New package migration adding a nullable unsigned integer `row_number` to `choice_list_entries` (register it in `getMigrations()` in the service provider, following the existing numeric ordering; up-only, no `down`).
- `XlsformTemplateChoicesImport` stores the row's position in the `choices` sheet in `row_number` (it already uses `RemembersRowNumber`; confirm in the implementation how the chunk offset behaves with `ToCollection`, and cover it with a test). Include `row_number` in the upsert's updated columns.
- Export orders by `coalesce(min(row_number), <max int>)`, then `min(id)`, so entries without a `row_number` (team-added entries, and every entry until the template is re-uploaded once) sort after the template entries and keep option A's behaviour.
- No backfill migration: one re-upload of each master xlsx populates it.

## Rollout order

1. Deploy items 1-5 (submodule commit, pointer bump, deploy, `php artisan queue:restart`). Item 4 means later re-uploads no longer reset the flags.
2. Data repair R1 and R2 (below), local clone first.
3. Set `can_be_replaced` with the SQL in "Operational steps".
4. Redeploy Alex's draft forms from the app so `localiseModules()` swaps in the Local versions.
5. Later, Phase 2: deploy item 6, then re-upload the three master xlsx files once.

## Data repair (one-off, run separately from the code deploy)

Implemented as one artisan command, `php artisan odk:repair-form-deploy-issues` (`app/Console/Commands/RepairFormDeployIssues.php`), instead of tinker snippets. It is a **dry run by default** and only writes with `--apply`; `--only=labels` or `--only=modules` runs one repair. Run it against a restored staging clone first, review the output, then Dan runs it on staging. See the [change log](../change-logs/2026-10-27-repair-form-deploy-issues-command.md).

- **R1: refill labels** (`--only=labels`). Lists the global choice entries that have no label (per template and list), then with `--apply` runs `AddMissingChoiceListStrings` for each template. Expected on staging: `crop_list` #28 global entries 0/35 -> 35/35 labelled; no other list changes. Needs the code deploy of item 1 first. Update: a clone of staging taken on 2026-10-27 showed `crop_list` #28 already fully labelled, so R1 is expected to report nothing; R2 behaved as expected (see the repair command change log).
- **R2: remove cross-template module versions** (`--only=modules`). For each form it lists the selected module versions whose module belongs to a different template than the form, and which modules would get their default version back; with `--apply` it detaches them, calls `syncWithTemplate()` and marks the form for a draft update. It only warns about a form that selects two versions of the same module of its own template (not changed). Affects forms 2, 3, 5, 6 on staging (v#38, v#41, v#48, v#51 were the foreign ones at the time of the investigation; the exact list comes from the dry run).

R2 does not touch the empty team-cloned versions (v#34-52); see "Open items". Teams 1 and 2 have empty Local versions, so do **not** turn on the swap for them until those are rebuilt (setting `has_updated_locations = 1` makes `Team::localiseXlsforms()` rebuild them on the next draft deploy); team 3 is safe to switch first.

## Operational steps (staging SQL, run by Dan)

### Turn on `can_be_replaced` for the five builder-backed modules

Only `locations` (all three templates) and `farm info` (Global Indicators and Women's) have a team-local builder. Farm Registration's `new farm info` (module 4) has none and must stay off.

```sql
SELECT m.id, m.xlsform_template_id, t.title, m.name, m.can_be_extended, m.can_be_replaced
FROM xlsform_modules m
JOIN xlsform_templates t ON t.id = m.xlsform_template_id
WHERE m.name IN ('locations', 'farm info')
ORDER BY m.xlsform_template_id, m.name;
-- expected ids: 3, 6, 7, 23, 24

UPDATE xlsform_modules
SET can_be_replaced = 1, updated_at = NOW()
WHERE id IN (3, 6, 7, 23, 24)
  AND name IN ('locations', 'farm info');
```

To make Alex's forms pick up the swap, mark them for redeploy and then deploy the drafts from the app:

```sql
UPDATE xlsforms SET draft_needs_update = 1 WHERE owner_id = 3;
```

### Reset a stuck translation counter

```sql
SELECT COUNT(*) AS pending_jobs FROM jobs;            -- must be 0 before resetting
SELECT id, creator_id, description, processing_count, updated_at
FROM locales WHERE id IN (5, 6);

UPDATE locales SET processing_count = 0 WHERE id = 6;  -- team 3 (Alex)
-- optional, stuck at 3 since 2026-10-20:
-- UPDATE locales SET processing_count = 0 WHERE id = 5;  -- team 2
```

Alex must then upload the Global Indicators translation again. Re-submitting also re-imports the Farm Registration and Women's files already attached (harmless, the import is an upsert).

## Tests

Package tests (Pest + Testbench). Extend `tests/Unit/Jobs/AddMissingChoiceListStringsTest.php` and `tests/Unit/Imports/XlsformModuleImportTest.php`; add an export and a model test.

1. `AddMissingChoiceListStrings`: two same-named lists; list A has a labelled team-owned entry and unlabelled global entries, list B has the labelled global entries. After the job, A's global entries are labelled (fails on current code). A team-owned entry with the same name but a different owner is **not** used as a source.
2. `XlsformChoicesExport` labels: two same-named lists on the form, labels only on the lower-id entry. The exported row for that name carries the label (fails on current code). A group where neither entry has labels still exports without error.
3. `Xlsform::localiseModules()`: a team has `Local locations` versions for module ids belonging to two different templates, the form belongs to the second template and uses the global default of a `can_be_replaced` module. After the call the form uses the version of **its own** module, and the other template's version is not attached (fails on current code). A module with `can_be_replaced = false` is left unchanged.
4. `XlsformModuleImport`: xlsx without `localisable_module` leaves an existing `can_be_replaced = true` untouched (fails on current code) and creates new modules with `false`; xlsx with `replace` sets it to `true`; xlsx with the column but a blank cell sets it to `false`.
5. `XlsformChoicesExport` order: entries inserted as `easy, medium, difficult, very_difficult, no_answer` export in that order (fails on current code); a team-owned entry added afterwards exports last.
6. Phase 2: the choices import records each entry's sheet position, and the export orders by it ahead of id; entries without a `row_number` sort last.

Run `./vendor/bin/pest` in the package, then the app suite, plus `phpstan` and `pint` as per project conventions.

## Expected outcome

| Item | Expected |
|---|---|
| Global Indicators deploy for any team | no `label node for itemset doesn't exist` error; `crop_list` 1-35 exported with all four labels |
| Contextualise choice lists page (`crop_list`) | global entries show their labels |
| Alex's forms (team 3) after steps 1-4 | locations and farm lookup come from the team's Local modules, no `loc_1_x` placeholders |
| Forms 2, 3, 5, 6 | only select versions of their own template's modules |
| Template re-upload | no longer resets `can_be_replaced` / `can_be_extended` when the xlsx has no `localisable_module` column |
| Exported choices | template order (`rapport`: easy, medium, difficult, very_difficult, no_answer; `reliability`: 5, 4, 3, 2, 1) |

## Findings without a fix in this plan

### Translation upload leaves the locale stuck on "Processing"

Evidence from the staging clone (locale 6, team 3): the third upload (Global Indicators file, 15:01:05) re-imported the Farm Registration strings (`updated_at` 15:01:06), did **not** touch Women's, and created **no** Global Indicators strings; `processing_count` ended at 1 with no pending job, no failed job and no failure notification. This matches [TeamTranslationReviewEditForm::submit()](../../app/Livewire/SurveyLanguages/TeamTranslationReviewEditForm.php) looping over every template that has an attached file (files are never removed after import) in the order Farm Registration, Global Indicators, Women's, incrementing `processing_count` before calling `Excel::queueImport()`: the request broke at the Global Indicators `queueImport()` call, after the increment, before the Women's iteration, so the counter was left incremented with nothing queued to decrement it. The actual exception should be in the staging `storage/logs/laravel.log` around 15:01. Locale 5 (team 2) has been stuck at `processing_count = 3` since 2026-10-20, so this has happened before; see [docs/issues/2026-08-02-locale-processing-count-stuck-risk.md](../issues/2026-08-02-locale-processing-count-stuck-risk.md).

Candidate fixes for a later plan (not decided): import only files uploaded in the current submit; increment atomically only after a successful dispatch and decrement in a `catch`; stop each chunk's validator from loading every survey row and choice entry of the template (it does so per 200-row chunk, about 1,470 models for Global Indicators).

## Open items

- **Team-cloned module versions are emptied by template re-upload.** `XlsformTemplateWorkbookImport::afterImport()` deletes every unflagged survey row under the template, including rows of team-owned clones. This empties `introduction`, `HDDS`, `Local locations` and `Local farm info` clones for teams 1 and 2 and leaves stray `end group` rows (`end group_567`, `womens_section`) in their forms. Team 3's clones will be emptied by the next re-upload.

## Follow-ups

Both were found during the local end-to-end test and fixed afterwards; see [the change log](../change-logs/2026-10-27-replace-xlsform-button-and-farm-choice-order.md).

- **Admin Panel > Xlsform Templates > Replace XLSForm (list row action) crashed** with `Filament\Actions\Action::getModalContent(): Return value must be of type View|Htmlable|null, array returned`. Fixed by using `->schema(...)` instead of `->modalContent(...)`.
- **Farm choices were listed newest first** in the form (F003, F002, F001), because Central serves the entity list newest first. Fixed for imports by sending the batch to Central in reverse.

## Out of scope

- Import/clone data-loss fix and the stray `end group` pre-flight check.
- Farm Registration `repeat_count` referencing a missing `participation` field (failed job on 10/20 and 10/26).
- `UpdateXlsformFile` failing with `temp/<id>/... .xlsx does not exist` (cause unknown; single worker, so not concurrency).
- Standalone farm import before locations fails validation by design; the combined wizard already chains the two imports in order.
