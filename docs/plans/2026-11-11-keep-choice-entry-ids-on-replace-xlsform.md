# Plan: Keep choice entry ids when "Replace XLSForm" re-imports a template

**Status: Not Started** — drafted 2026-11-11, waiting for Dan's review. Follow-up to [the Replace XLSForm fix](2026-11-09-prune-stale-rows-on-replace-xlsform.md) ([change log](../change-logs/replace-xlsform-keeps-team-versions.md)); that fix is deployed to staging.

The fix lives in the `packages/filament-odk-link` submodule (`XlsformTemplateChoicesImport`), with tests in the submodule and in this repo. Implementing it means a commit on a submodule branch started from `staging-hotfix-20261026`, then a submodule pointer bump here. Dan commits.

## Terms used in this plan

| Term | Meaning (class / table) |
|---|---|
| **Choice entry** | One option of a select question (`ChoiceListEntry`, table `choice_list_entries`). It belongs to a choice list, which belongs to a module version. |
| **Global entry** | A choice entry with `owner_id` NULL: it comes from the template file. Entries with an `owner_id` were added by a team and are not part of the file import. |
| **Default locale** | The locale of a language created by the template import (`locales.is_default = 1`). Its language strings come from the `label::<language>` columns of the template file and are re-imported on every Replace. |
| **Team locale** | A locale a team created on the Survey Languages page, for example "Spanish (Guatemala)" (`locales.is_default = 0`, `creator_id` = the team). Its language strings come from the translation files the team uploads. A team uses it instead of the default locale of that language (`language_owner`). |
| **Language string** | One translated text (`language_strings`), linked to a survey row or a choice entry by `linked_entry_type` / `linked_entry_id`, and to a locale. |

## Problem

After a Replace XLSForm on staging, the team forms of "Guatemala - ARGIDIVI" show their options (the choices of select questions) blank when Spanish is selected in Enketo. The questions are in Spanish for the Global Indicators and Women's forms.

### What the data shows

- The generated team form files on staging have the Spanish option labels mostly empty: Farmer Registration 36 of 43, Global Indicators 119 of 525, Women's Form 36 of 147 (the English column is filled for all of them).
- In a copy of the staging database, the team's Spanish locale (locale 6) has a label for 0 of 160 global choice entries of the Women's template and 41 of 568 of the Global Indicators template, while the default Spanish locale has all of them.
- All 160 global choice entries of the Women's template in that copy were created in the same second (2026-11-04 12:16:47), which is the time of an earlier Replace: every Replace re-creates the entries.
- In the same copy, 52 language strings of the team's locale point to choice entries that no longer exist.
- Survey rows are not affected: they keep their ids across a Replace, and the team's Spanish question labels are still there (385 of 390 and 179 of 183 rows).

### Root cause

`XlsformTemplateChoicesImport::collection()` imports the choices sheet with `ChoiceListEntry::upsert($rows, ['name', 'choice_list_id', 'cascade_filter'])`. The table has a unique index on exactly these columns (`unique_name_choice_list_id`), but `cascade_filter` is nullable and most entries have NULL there. A unique index never treats NULL as equal to NULL, so the upsert finds no existing row and inserts a new one. The `afterImport()` cleanup then deletes the old rows (they were not updated during the import), and the `deleting` hook of `ChoiceListEntry` deletes their language strings.

For the default locales this does no harm, because their strings are imported again from the file for the new rows. A team locale is never in the file, so its strings for the options are lost on every Replace. This was true before the Replace XLSForm fix (the entries of the Women's template were last re-created on 2026-11-04) and is not changed by it.

Why it was not noticed: a team on a default locale always looks fine, and the team's questions keep their translation.

### How this was verified

A throw-away test imported `tests/assets/replace-xlsform-v1.xlsx`, created a team locale with a label for every choice entry, then imported `replace-xlsform-v2.xlsx`: 0 of 14 entries kept their id, and the team locale strings went from 14 to 0. The same test for survey rows keeps the ids and the strings (the rows that are not in the new file lose theirs).

## Design

### 1. Match the existing global entries before the upsert

In `XlsformTemplateChoicesImport::collection()`, for the rows of a chunk:

1. Load the existing global entries of the choice lists in scope: `ChoiceListEntry::withoutGlobalScopes()->whereNull('owner_id')->whereIn('choice_list_id', $choiceLists->pluck('id'))`, selecting `id`, `choice_list_id`, `name`, `cascade_filter`. Build a map keyed by `choice_list_id`, `name` and `cascade_filter`, where a NULL `cascade_filter` equals another NULL. If the same key exists more than once, the first id wins; the other rows are not updated, so the existing `afterImport()` cleanup removes them.
2. For every row that has a match, add the existing `id` and update it with `ChoiceListEntry::upsert($rowsWithId, ['id'], ['properties', 'updated_during_import'])`. The key columns do not change, so they are not updated.
3. Rows without a match go through the current `upsert` call unchanged, so new entries and duplicated non-NULL keys behave as before.

The import already loads `choiceListsForImport()` (global versions only for a template), so the scope of the lists does not change.

### 2. What does not change

- Entries that are not in the new file are still deleted by `afterImport()`, with their language strings.
- An entry whose name, list or `cascade_filter` changes in the file is a new entry; the team's strings for the old one are lost. This is unavoidable without a stable identifier in the file.
- The default locale strings are imported as before (`uniqueBy` locale, entry, type and string type, all non-NULL).
- Team-owned entries (`owner_id` set) are never matched or updated by the file import.
- Cloned versions stay snapshots (unchanged decision).
- No migration: changing the unique index or turning NULL into an empty string would need a data migration and could change what other code sees in `cascade_filter`.

### 3. Existing damage is not repaired (decision by Dan, 2026-11-11)

The team translations of options that were already deleted by earlier Replaces are not restored, and no repair command is added. The team uses its own Spanish translation and re-uploads it. Two things follow:

- The team has to export a fresh translation file after this fix is deployed. Files exported before contain the ids of entries that no longer exist, so the upload check rejects them or the strings point to nothing.
- From that upload on, the translations survive later Replaces.

## Tests (Pest)

Package tests (new file `tests/Unit/Imports/XlsformTemplateChoicesImportTest.php`, or the nearest existing place), each checked to fail with the fix reverted:

1. Importing the same choices twice keeps the ids of all global entries.
2. A language string of a team locale on an entry is still there after the second import, and still linked to the same entry.
3. An entry that is removed from the file is deleted together with its language strings; a new entry is inserted.
4. Entries with a non-NULL `cascade_filter` keep their ids, and two entries with the same name and different filters in one list are both kept.
5. A team-owned entry with the same name in the same list is not matched and not changed.
6. `properties` of an existing entry are updated from the new file.

Host test: extend `tests/Feature/ReplaceXlsformKeepsTeamVersionsTest.php` (real v1 to v2 import): add a team locale with a label for every choice entry before the second import, and check that the entries keep their ids and the strings survive. The fixtures may need a list with a `filter` column for case 4; if not, build that case directly in the database.

Also run the full host and package suites and PHPStan (no new errors), as for the earlier fix.

## Manual verification

1. Local: Replace the same template file twice; the ids of its global choice entries (and their count) must not change; a team locale string added to an entry before the second Replace is still there.
2. Staging, after deployment: note `MIN(id)`, `MAX(id)` and `MIN(created_at)` of the global choice entries of one template, Replace that template with the same file, and check that they are unchanged and the entry count is the same. Then ask the team to export a fresh translation file, fill in the options and upload it; after one more Replace the Spanish options must still be there.
3. Create the drafts again and open the form in Enketo with Spanish selected.

## Rollout

1. Implement and test in the submodule (branch from `staging-hotfix-20261026`), merge it there, then bump the pointer here (same order as the earlier fix).
2. Deploy to staging, restart the queue workers (the import runs in the queue) and reload PHP-FPM.
3. Run the manual verification above. There is no production environment yet.

## Risks

- A bug in the matching could update the wrong entry. Mitigation: the key is the full unique key, restricted to global entries, and the tests above.
- Large templates: the lookup is one query per chunk of the file (1,000 rows), for the lists in scope; the largest staging template has about 590 global entries per list set. No performance problem is expected.
- The first Replace after the deployment keeps the entries created by the last one, so the translations the team uploads from then on are safe; nothing before that can be recovered.

## Out of scope

- Repairing or copying translations (Option A, declined 2026-11-11).
- Falling back to the default locale text when a team locale has no string. The generated file uses only the team's own locale; this stays as it is.
- Regenerating the team forms when a team adds a language or uploads a translation (today a form keeps its old file until something flags it, so a language added later does not reach already generated drafts).
- The Farmer Registration questions of the Guatemala team: their "Spanish (Guatemala)" strings for that template are English text (17 of 18); the team has to translate them.
- The `has_locales` cast on `Xlsform` without a matching column, the `PDO::MYSQL_ATTR_SSL_CA` deprecation on PHP 8.5 in `config/database.php`, and the PhpSpreadsheet deprecation notices.
