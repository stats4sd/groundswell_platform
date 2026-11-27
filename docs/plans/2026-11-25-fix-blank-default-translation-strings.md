# Plan: fix blank default-language cells in the translation files

**Status: Completed.** PR 1 to 3 were deployed to staging and the data steps (R1, R4) and the UI and ODK checks passed on 2026-11-27. Change log: [docs/change-logs/2026-11-27-fix-blank-default-translation-strings.md](../change-logs/2026-11-27-fix-blank-default-translation-strings.md). Reviewed four times against the code on 2026-11-25 (see "Review notes" at the end).

Branches: main repo (plan, change log, tests, submodule bump); submodule `packages/filament-odk-link` (code and its own tests), PR base `staging-hotfix-20261026`. One PR per item group (see "Order of work").

## Problem

Alex (Women's Form and Global Indicators, Spanish (Guatemala)) reported blank "English (default)" and "Spanish (default)" cells on some rows of the downloaded translation files. Investigated on a clone of the staging DB (read only) on 2026-11-25. There are three separate causes.

### A. Choice lists have lost their default-locale strings (real data loss)

- 21 choice lists (138 entries) in templates 2 (Global Indicators) and 3 (Women's Form) hold no default-locale (English, Spanish, French, Nepali) strings. Lists: `yes_no` ×13 (9 in template 2, 4 in template 3), `crop_list`, `months`, `proportions` ×2, `who_family` ×3, `livestock_list_name`.
- All 138 entries still have team-locale strings, so teams' own translations are intact. Every entry has a donor entry in another list of the same template with the same `list_name`, `name`, `properties` and `cascade_filter` that still holds the default strings. Checked on the clone: the donors never disagree (no entry key has two different English labels across the default lists of templates 2 and 3). So all are repairable without a re-import.
- Two defects combine:
  1. `XlsformTemplateLanguageStringImport::model()` takes `$items->first()` among same-name choice lists, so only one list receives the default string. `afterImport()` then deletes default-locale strings with `updated_during_import = false`, which removes the strings of the other same-name lists.
  2. `AddMissingChoiceListStrings` is the repair step, but it skips every entry that has any string (`reject(... languageStrings->isNotEmpty())`, line 61), team strings included. These entries have team strings, so they are never repaired. Its donor selection has the same blind spot: a list counts as a donor when it holds any string, so a broken list that only has team strings can be picked as the donor (the job takes the first matching list) and nothing useful is copied.
- Effect: the default columns of the file are blank; and the default-locale fallback added on 2026-11-17 has nothing to fall back to, so a team without its own text gets blank labels.
- When the strings were lost is unknown (never created, or deleted at a Replace XLSForm; the latest was 2026-11-17).

### B. The file lists `end group` rows (not data loss)

- Most blank survey rows are `end group` rows (type values `end group` 316, `end_group` 30, `end repeat` 6). They carry team-locale strings only, so the export lists them, with blank default columns.
- ODK does not use the label of an end row. The strings come from an old name-based import that copied the begin group's title onto the end row, and from later uploads.
- 64 such rows in template 3 carry 192 strings:

| Locale | Content |
|---|---|
| 5 (team 2) | 8 blank, 56 group titles copied from begin rows (53 identical to the begin row; one unique and outdated: `vcc_module`, "CONTROL DE ELECCIÓN DE VOZ") |
| 6 (team 3) | 64 blank |
| 8 (team 6) | 64 × "Módulo para la recopilación de datos de indicadores centrados en las mujeres" |

- The locale 8 text is the hint of `welcome_title`. Alex confirmed the cause: he builds the file with XLOOKUP, and a blank English cell matches that one value. It is not written by the code (an export, import round trip changes nothing).
- Effect on forms: in xlsform 30 (team 6), 39 end rows carry that text as their label. ODK ignores it. Not verified by deploying.
- Not affected: `end` (4 rows) is the ODK metadata field for the end time, not a group end. Never skip it. `begin group`, `begin_group`, `begin repeat` and `begin_repeat` rows are real containers whose labels ODK uses, so they stay in the file and the import.
- Two `end group` rows are kept as they are: `location2` (ids 40 and 4553, Farmer Registration) carries real default text (Location, Ubicación, Emplacement, स्थानहरू) and, between them, two team-locale strings ("Ubicación" in locales 6 and 8). Skipping them in the export and import is enough; the cleanup must not match them (see item 5).
- Only the export reads these rows for translation (`surveyRowsForTranslation()` has no other caller), so no other screen needs the filter.

### C. Saving re-imports every attached file, and blank cells overwrite translations

- `TeamTranslationReviewEditForm::submit()` loops over all templates and re-imports every file already attached to the locale, not only the one just uploaded. On 2026-11-24 the save of a new Farmer Registration file also re-imported the older Women's and Global files (media 230 and 226), overwriting later edits. (Alex confirmed the 11-24 upload was his: he downloaded the existing file and uploaded it.)
- A blank cell in an uploaded file overwrites the existing translation (`XlsformTemplateLanguageImport` writes `$row[idx] ?? ''`). Decision (Dan, 2026-11-25): keep this behaviour, but warn before it happens.
- `LanguageString::text` stores null as a single space (`set: fn ($value) => $value ?? ' '`), so the database can hold `' '` as "blank". Every blank check must trim.

## Decisions (agreed 2026-11-25)

1. Fix A in code and repair the data on staging.
2. Skip only `end group`, `end_group`, `end repeat`, `end_repeat` in the export and the team upload. Do not skip `end`, nor any `begin ...` type.
3. Delete the team-locale strings of end rows that have no default-locale string (192 on the clone), after a backup. Keep `location2`: it has default strings, so it is not matched. (A plain "non-default locale" criterion matches 194 on the clone and would delete the two "Ubicación" strings of `location2`.)
4. Import only the file(s) uploaded in this save.
5. Blank cells keep overwriting. Add a confirmation before anything is saved or imported when it would clear existing translations.

## Fix

### 1. Default strings reach every same-name choice list (A, code)

- `XlsformTemplateLanguageStringImport::model()`: for the choices sheet, return one `LanguageString` per matching entry (an array; `ModelManager::toModels()` wraps it, so `ToModel` and `WithUpserts` accept it). The match stays `list_name`, entry `name`, `properties` and `cascade_filter` within the imported model. Survey rows are unchanged (matched by name and type): same-named survey rows in different modules may legitimately carry different labels, and writing one label to all of them would make them identical.
- Only entries with `owner_id` null are matched. In a queued or console run the owner scope does not apply, so a team's own entry with the same list, name and properties would otherwise receive the template's default string (`afterImport()` already protects team entries from deletion; the write side must protect them too).
- Every entry written gets `updated_during_import = true`, so `afterImport()` stops deleting their strings.

### 2. `AddMissingChoiceListStrings` repairs entries that lack default strings (A, code)

Two cases, both create-if-missing per (locale, string type), never overwriting an existing string:

- **No strings at all** (today's case, any owner): copy all donor strings, as today. Unchanged, so the four existing tests in `packages/filament-odk-link/tests/Unit/Jobs/AddMissingChoiceListStringsTest.php` keep passing (including "does not copy a label from a team entry owned by a different team").
- **Has strings but no default-locale string** (new): only global entries (`owner_id` null) in lists of default module versions (`choiceListsForImport()` when the model is a template; the version's own lists when it is a module version, and only if that version is a default one). Copy only the donor's default-locale strings. Team-owned entries are not touched.

Donor selection, for both cases:

- Same `list_name`, same template or version as the model, not the list being filled, and the donor entry matches `name`, `properties`, `cascade_filter` and `owner_id`.
- The donor entry must hold what is being copied: any string for the first case, a default-locale string for the second. A list is a donor candidate only if it has such an entry. (Without this, a broken list with team strings is picked first and nothing is copied.)
- For the second case, donor lists are limited to default module versions (`is_default`), like the target lists. A cloned version may carry edited text, and the clone check that donors never disagree covered default versions only.
- Donor lists are ordered by id, so the result is deterministic.

Known limit: an entry that has a default string in one default locale (say English) but lacks another (say Spanish) is not repaired. The verification step counts these so the gap is visible.

### 3. One-off data repair on staging (A, data)

- Extend the existing R1 ("labels") of `odk:repair-form-deploy-issues` instead of adding a command: `unlabelledGlobalEntriesByList()` must report entries without default-locale strings as well. The command keeps its convention: dry run by default, `--apply` writes (the plan's earlier `--dry-run` flag is dropped).
- Scope: global entries in lists of default module versions. Checked on the clone with exactly this scope (global entries, default versions, no default-locale string): template 2 has 17 lists / 126 entries, template 3 has 4 / 12, together 21 lists / 138 entries, none left after `--apply`. If the dry run prints a different count, stop and look before applying. The dry run also reports, without changing them, the entries outside this scope that lack default strings: on the clone, 2 lists / 35 entries in a non-default (team clone) version of template 1, which hold team strings only (see Open decisions).
- Idempotent: a second `--apply` changes nothing.
- Before applying: dump `language_strings` (`mysqldump`). The run only creates rows; for a rollback it prints its start time, and the rows to delete are the `language_strings` created after that time on the entries it listed.
- Forms already deployed do not change until regenerated. `LanguageString` has no model events and the job writes through the query builder, so the repair does not set `draft_needs_update` by itself (a saved choice entry does, a language string does not). Proposed: `--apply` also sets `draft_needs_update = true` on the forms that select the repaired module versions, so the existing "Update test forms" action shows the change (see Open decisions). After the repair, redeploy the draft forms of the affected teams and check that the default labels reach the xlsx (see verification).

### 4. Export and import skip end rows (B, code)

- One definition of the skipped types: a method on `SurveyRow` (for example `isGroupEnd()`) plus a matching query scope, comparing `trim(lower(type))` with spaces and underscores treated as equal, against `end group` and `end repeat` only.
- `XlsformTemplateTranslationsExport::buildCollection()`: reject those survey rows (`styles()` reads the same cached collection, so the row count stays consistent).
- `XlsformTemplateLanguageImport`: `model()` has a non-null return type and the file only carries entry ids, so skip in `isEmptyWhen()`. Load the set of end-row ids of the template once per import instance (lazily, a non-serialized property reloaded per chunk job), and return true for a `survey` row whose id is in the set. `TranslationUploadInspector::validate()` does not change: end rows are known ids.

### 5. Remove the end-row strings (B, data)

- Add repair "ends" to `odk:repair-form-deploy-issues` (`--only=ends`), dry run by default.
- Criteria: language strings in a non-default locale (`locales.is_default = false`), linked (through the `linkedEntry` morph relation, as `afterImport()` does) to survey rows whose type is one of the end types and that have **no default-locale string at all**. The last condition is what keeps `location2` (it has default strings). No template or id filter. The dry run prints the count per locale. Clone value: 192 (locale 5: 64, locale 6: 64, locale 8: 64). Without the last condition the count is 194.
- `--apply` requires `--expect=N` and aborts when the count differs, so a staging that drifted from the clone is noticed.
- Before deleting, the command writes the rows to a JSON file under `storage/app` and prints the path; a restore is a re-insert from that file. Also take the `mysqldump` of item 3.

### 6. Save imports only the uploaded files, with a warning first (C, code)

`submit()` becomes two steps, and nothing is saved or queued before the warning is answered.

1. Permission check and `$this->form->validate()`. Not `getState()`: `Schema::getState()` calls `saveRelationships()` itself (found while implementing; a test showed the media was stored before the warning), which would attach the file before the user answered. `validate()` checks the form and stores nothing.
2. Pending uploads: per template, the `TemporaryUploadedFile` values in `$this->data["upload_for_template_{id}"]` (they exist until `saveRelationships()` stores them; `BaseFileUpload` keeps them in the raw state).
3. Inspect each pending temp file with `TranslationUploadInspector`. Do not pass the Livewire temp file straight in: `TemporaryUploadedFile::getRealPath()` is `storage->path()`, which only works when the Livewire temp disk is local (`LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK` may point elsewhere in production). Write `$file->get()` to a temporary local file, inspect that path, and delete it afterwards (the Spatie upload field reads the file the same way). On errors: notify, delete the temp file, remove it from the state, continue with the other uploads. (Nothing is attached to the locale, so no media has to be deleted.) If the user had removed the template's existing file with the 'x' before uploading the rejected one, that existing file is put back into the field state (`keepExistingUploadFor()`), because `saveRelationships()` would otherwise delete it as abandoned: a rejected replacement must not leave the template without its last working file (found in browser testing, scenario 8).
4. For the valid ones, count the cells that would erase a translation: the text cell is blank (trimmed) and the database holds a non-blank (trimmed) string for that entry, string type and locale. End rows are not counted. One query per template, in chunks of ids. Nothing is written.
5. If the count is above 0: set a public `$overwriteWarnings` array (template title and count) and stop. The component view shows an inline warning box with "Continue" and "Cancel" buttons (not a Filament modal: the form already lives inside a modal and the view has no `<x-filament-actions::modals />`). "Cancel" clears the warning; the temp uploads stay in the form so the user can replace the file. "Continue" calls `confirmOverwrite()`, which does the permission check and runs steps 1 to 4 again. It goes on to step 6 only if every template's blank-over-existing count is no higher than the count that was shown; otherwise it shows the warning again. (Without this, a file added after the warning appeared would be saved and imported unvalidated.)
6. Persist and import: remember the ids of the locale's media, call `saveRelationships()` (it also processes the 'x' deletions), refresh, and take the media that are new. For each new file, re-inspect it from its stored path (for `textColumnIndex`), call `LocaleImportCounter::started()` once with the number of imports, queue each import with its notification chain. Files that were attached earlier are never read or imported.

Notes:

- The warning and the confirm step recompute from the temp files, so nothing large is kept in the Livewire state.
- If the component is closed at the warning, nothing was saved, so the locale cannot show "uploaded" while its strings were never imported.
- The existing tests that pre-attach media and then call `submit` rely on the stale-file behaviour. They must upload through the form state instead (see Tests).
- Found while testing in the browser: the edit form lives in the Filament `view-edit` table action modal, whose content sits inside the modal's `<form>`. A button without `.prevent` also submits that form (`callMountedAction` on `TeamTranslationEntry`), which closes the modal. So Submit and the warning buttons use `wire:click.prevent`, and `TeamTranslationEntry::closeModal()` now calls `unmountAction()` so the `closeModal` event closes the modal explicitly (Cancel and Duplicate keep their old behaviour and still close it).

## Tests

Package (`packages/filament-odk-link/tests`, own suite):

1. Import (there is no test of `XlsformTemplateLanguageStringImport` today, so this needs its own small xlsx fixture): a default string for a `list_name` that exists in two lists gives both lists' entries the string, and `afterImport()` keeps them; a team-owned entry with the same list, name and properties gets nothing; same-named survey rows still only get the first match.
2. `AddMissingChoiceListStrings`: fills a global entry that has team strings but no default strings, from a donor with default strings; does not change its team strings; picks the right donor when another same-name list also has only team strings (the reviewed failure); leaves team-owned entries alone; a second run changes nothing; all four existing tests pass unchanged.

Main repo (`tests/Feature/...`):

3. R1 dry run reports the entries, changes nothing; `--apply` fixes them; the existing `RepairFormDeployIssuesTest` cases pass.
4. Repair "ends": dry run counts per locale; `--apply` without `--expect` or with a wrong count aborts; with the right count it deletes only the non-default-locale strings of end rows without a default string, keeps `location2` (default strings plus team "Ubicación" strings) and every default-locale string, and writes the backup file.
5. Export: end rows (all four spellings, odd case and spacing) are absent; `end` metadata rows, begin rows and `location2` stay; `styles()` row count matches.
6. Import: a file containing an end row writes no string to it; other rows import as before; a row of the `choices` sheet whose id equals an end row's id is not skipped.
7. `submit()` with one new upload imports one template and not the other attached files (the stale-file case of 2026-11-24).
8. Warning: blanks over existing text (also over `' '` as "blank") show the warning and persist/queue nothing; "Cancel" leaves the media untouched; "Continue" imports; a file added after the warning is validated and counted again, and a higher count shows the warning again; blanks over nothing, or no blanks, import without a prompt; a rejected file is removed and the others still import. This is also the first test to write: it proves that `TemporaryUploadedFile` values stay in `$this->data` between requests.
9. The existing edit-form tests that pre-attach media and call `submit` (round trip, wrong headers, import counting, queue failure) are rewritten to upload through the form state; their assertions about counts, notifications and the processing counter stay the same.

Then `./vendor/bin/pest` (main and package), `./vendor/bin/phpstan analyse`, and `./vendor/bin/pint` on the changed files only. Package code follows its guidelines (no comments, no single-letter variables, nested `if` instead of `&&`, braces).

## Order of work (one PR each)

1. Items 4 and 6 (export and import skip end rows; save only the uploaded files; warning). Code only. This spans both repos: the submodule PR (`SurveyRow::isGroupEnd()`, export) goes first, then the main PR with the submodule bump (import, edit form, view). New UI strings use `t()` (follow the placeholder style of existing `t()` calls); Dan runs the translation sync. Before starting: confirm the base branches with Dan, create the branches with `--no-track`, set this plan's Status to In Progress with a short progress paragraph, and write the change log when an item group is done.
2. Items 1 and 2 (default strings reach all same-name lists; improved repair job).
3. Items 3 and 5 on staging (data), after PR 1 and 2 are deployed.

## Verification on staging

1. Before anything: `mysqldump` of `language_strings`. Keep Alex's current files.
2. R1 dry run: 21 lists, 138 entries. `--apply`: none left. Also run the query that counts entries with a default string in one default locale but not another that a donor has; expected 0, otherwise list them in the change log.
3. Download the Women's and Global files for a team locale: no blank default cells on choice rows.
4. Redeploy a draft form of team 6 and read the xlsx from ODK Central: choice labels present; the end group rows have no label (the 39 rows are blank) and the form loads. The repair does not flag forms by itself (item 3), so confirm the flag the command sets.
5. "ends" dry run: 192 (not 194). `--apply --expect=192`. The Women's file of each team no longer lists end rows. Only the end rows in the module versions the team's form selects were ever listed, so the drop is per team (team 6: 510 rows in Alex's file, 471 after the fix, 39 fewer; the other 25 of the 64 are in versions it does not select). `location2` still has its default and team strings.
6. Upload a file with a blank cell over an existing translation: the warning shows the count and nothing is saved until "Continue". Close the form at the warning: no new media.
7. Other templates already attached: upload one template; only that one shows an import notification.

## Notes

- The R4 dry run on the local clone reports 256 strings, not 192: the clone has a fourth locale (9, the "Duplicated" Spanish (Guatemala) created while testing the Duplicate button) that copied the 64 junk strings of locale 8. This is what `--expect` is for. On staging, run the dry run first and expect 192 plus 64 for every duplicate of that locale that exists there; run `--apply --expect=N` only with the number the dry run printed and after checking where each locale comes from. `--only=ends` is never part of a full run, and R1 `--apply` writes a log (`storage/app/private/repair-backups/`) with its start time and the repaired entry ids for an undo.
- Alex should hold uploads until PR 1 and the data repair are deployed, then download fresh files and rebuild. His lookups should use `entry_id` (column C), not the English text, because any row that still has a blank English cell (for example the 3 Global hint and constraint_message cells that have no default text in the template) would again match the first blank. `entry_id` is stable only until the next Replace XLSForm.
- The overwrite warning only counts blank-over-existing. It does not catch junk text written over a blank or over real text. Fresh files from the fixed export are the protection.
- Out of scope: blank cells overwriting translations (kept), the 3 Global blank hint and constraint_message cells (no default text in the template), the `location2` end strings, partial default coverage (see item 2).

## Review notes (2026-11-25)

Third review changed: the cleanup criterion is "non-default locale and the end row has no default-locale string" (the earlier "non-default locale, no id filter" would have matched 194 strings and deleted the two team "Ubicación" strings of `location2`, and `--expect=192` would have aborted); "Continue" re-validates and re-counts; the temp upload is copied to a local file instead of `getRealPath()`; the import writes only to entries with `owner_id` null; donors for the new repair case are limited to default versions; the repair prints its start time for a rollback; the missing test for the string import is called out; PR 1 spans both repos; Maatwebsite skips rows (`isEmptyWhen`) before validating, so skipped end rows are never validated.

First review changed: donor selection must require default strings; default-only copy would regress the no-strings case; the warning moved before `saveRelationships()`; the end-row mechanism on import; repair scope limited to default versions; R1 extended instead of a new command (`--apply`, not `--dry-run`); the 192 count is checked with `--expect` instead of hard-coded; blank means trimmed.

Second review added: `LanguageString::text` stores `' '` for null; existing edit-form tests pre-attach media and must change; the confirmation is inline (no modals component in the view); end-row id set on import is lazy and not serialized; the repair command writes its own backup file; forms already deployed need a redeploy after the repair; cleanup criteria use "non-default locale" and no id filter; donors checked on the clone for disagreement; `surveyRowsForTranslation()` has no other callers.

## Decisions on the open points (Dan, 2026-11-25)

1. `--apply` of the repair sets `draft_needs_update = true` on the forms that select the repaired module versions (yes). It is the only signal teams get that the generated form changed, and it is cheap.
2. The 35 entries (2 lists) in a non-default version of template 1 that lack default strings stay outside the repair (leave them for now).

## Fourth review (2026-11-25)

Changed: the new repair case runs for a module-version model only when that version is a default one; the expected 21 lists / 138 entries was re-checked on the clone with the exact scope of the repair (17 / 126 in template 2, 4 / 12 in template 3), and the 35 entries outside it were found; confirmed that `LanguageString` has no events, so the repair does not flag forms; `t()` placeholder and branch notes added; open decisions listed.
