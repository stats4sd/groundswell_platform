# Change log: fix blank default-language cells in the translation files

Implements [docs/plans/2026-11-25-fix-blank-default-translation-strings.md](../plans/2026-11-25-fix-blank-default-translation-strings.md).

## Problem

Alex (Women's Form and Global Indicators, Spanish (Guatemala)) found blank "English (default)" and "Spanish (default)" cells in the downloaded translation files. There were three causes:

- **A. Lost default strings (data loss).** 21 choice lists (138 entries) in templates 2 and 3 had no default-locale strings. The language string import wrote the default string to the first same-named list only, and its cleanup then deleted the strings of the other same-named lists. The `AddMissingChoiceListStrings` repair job skipped any entry that already had a team string, so it never repaired them.
- **B. End rows in the file.** `end group` / `end repeat` rows were exported and could be imported back, so team locales gained strings on rows that have no label (192 strings in locales 5, 6 and 8). Their default cells are blank by design.
- **C. Master template without English text.** Some survey cells have no English text in the master template (for example Women's `welcome_title` hint). This is not a bug and was not changed. Translators should match on `entry_id` (column C), not on the English text.

## Changes

Submodule `packages/filament-odk-link` (PR base `staging-hotfix-20261026`):

- `SurveyRow::isGroupEnd()` and `scopeGroupEnds()` (type compared case-insensitively, `_` = space).
- The translation export skips end rows.
- `XlsformTemplateLanguageStringImport` writes a default choice string to every same-named global list; survey rows still take one row.
- `AddMissingChoiceListStrings` also restores default-locale strings for global entries that only have team strings (default-version lists only, donors need default strings and must match on name, properties and cascade filter).

Main repo:

- The translation import skips end rows.
- Save imports only the files uploaded in that save (it used to re-import every file attached to the form).
- An inline warning appears before blank cells erase existing translations (Continue / Go back); nothing is stored before the user confirms.
- A rejected replacement file keeps the previous working file.
- The Cancel / Go back buttons no longer submit the surrounding modal form; `closeModal` unmounts the action.
- `odk:repair-form-deploy-issues`: R1 repairs choice entries without labels or default strings (dry run by default, `--apply` writes, backup JSON per template, flags affected forms with `draft_needs_update`). R4 (`--only=ends`, never part of a full run) deletes team-locale strings of end rows that have no default string; it needs `--apply --expect=N` and writes a backup JSON.

## Tests

- Main suite 482 passed; package suite 280 passed; phpstan and pint clean on the changed main-repo files.
- New: `TranslationUploadFlowTest`, import/export cases for end rows, R1 and R4 command tests (run against a fake disk), package tests for the import, the repair job and an end-to-end xlsx import.

## Staging deployment and verification (2026-11-27)

Data steps, run on staging after deploying the code:

| Step | Command | Result |
|---|---|---|
| R1 | `odk:repair-form-deploy-issues --only=labels --apply` | Template 2: 126 entries, template 3: 12 entries repaired; 552 default strings created (default locales 1 to 4: 18856 to 19408); team strings unchanged; 6 forms per template flagged; a second dry run reports 0 |
| R4 | `odk:repair-form-deploy-issues --only=ends --apply --expect=192` | 192 strings deleted (locales 5, 6, 8: 64 each); locales 5 / 6 / 8 now 779 / 2609 / 2653; a second dry run reports 0 |

Backups (kept in `storage/app/repair-backups/` on staging): `choice-label-repair-template-2-20261127-144804.json`, `choice-label-repair-template-3-20261127-144806.json`, `end-row-strings-20261127-145205.json`. R4 restore = insert the rows of the JSON again.

UI checks on a QA locale (id 9): export has no end rows; Save imports only the new file and only that template and locale were updated; the warning, Continue, Go back and Cancel work; a file with a bad header is rejected and the previous file stays; Alex's updated file writes 0 end-row strings; Duplicate writes 0 end-row strings and leaves locales 5, 6 and 8 unchanged.

ODK check (Women's Form, team 6): after Update the XLSForm has 42 `end group` / `end repeat` rows with no label and 147 choices, all labelled; `yes_no` shows Yes / Sí, No / No, No answer / Sin respuesta; the form loads in Enketo, Spanish works and "Data is valid" is shown in preview. Actual submission was not tested (preview cannot submit).

## Not changed (decisions)

- 35 team-copied choice entries in 2 lists of template 1, in versions that are not default versions: left alone.
- Survey cells with no English text in the master template (cause C): not a bug.
- Alex's file has to be downloaded again; the old files still contain end rows and blank cells.
