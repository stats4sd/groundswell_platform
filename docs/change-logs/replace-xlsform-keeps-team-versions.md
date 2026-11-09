# Change log: "Replace XLSForm" keeps team-owned module versions and adds new modules to team forms

Implements [docs/plans/2026-11-09-prune-stale-rows-on-replace-xlsform.md](../plans/2026-11-09-prune-stale-rows-on-replace-xlsform.md) (Traditional Chinese: [zh-TW](../plans/2026-11-09-prune-stale-rows-on-replace-xlsform.zh-TW.md)).

## Problem

After Replace XLSForm, creating drafts for the team forms failed on ODK Central (`Unmatched 'end_group'`, `relevant ... Could not find the name 'participation'`). Two causes, both reproduced locally on 2026-11-09:

1. The cleanup at the end of a template import ran over every version of the template, including team-owned Local and cloned versions, and deleted their survey rows, language strings and (for empty lists) choice lists.
2. Modules that the new file introduced were never attached to existing team forms, and when they were, they tied with an existing module's order.

## Changes (all in the `packages/filament-odk-link` submodule unless noted)

### Import scope: the file import only touches global versions
- `XlsformTemplate`: new `surveyRowsForImport()`, `choiceListsForImport()`, `choiceListEntriesForImport()`, `surveyLanguageStringsForImport()`, `choiceListEntryLanguageStringsForImport()` (the existing deep relations plus `is_default = true`).
- `XlsformModuleVersion`: the same five methods, returning its plain relations, so a team's custom module upload behaves as before.
- `XlsformTemplateWorkbookImport::afterImport()`: cleanup of survey rows and choice entries uses the import-scope relations; the empty choice list delete is limited to the imported versions' lists and goes through Eloquent (it used to delete every empty choice list in the database).
- `XlsformTemplateLanguageStringImport`: cleanup uses the import-scope relations; `model()` looks rows and entries up in the import scope (loaded once per import), so a string can never be attached to a same-named row of a team copy.
- `PrepareSurveyRowPaths`, `XlsformTemplateChoicesImport`, `FinishChoiceListEntryImport`: use the import scope. Without this, once team copies survive, their overlapping row numbers corrupted the `path` of global rows (confirmed by a test), and new choices were written into team copies.

### New modules reach existing team forms
- `FinishXlsformTemplateImport` (template case): calls `syncWithTemplate()` on every team form of the template, flags them `draft_needs_update`, and logs the team-owned versions of the template (a warning for any with no survey rows).
- `Xlsform::syncWithTemplate()`: for a form that already has modules, a module added to the template is inserted after the module that precedes it (including that module's Local version) and later modules move back by one or two positions. Relative order, including a team's own order, is kept. A form with no modules is attached as before.

### Repair command (host app)
- `odk:repair-form-deploy-issues --only=versions` (R3, also part of a run without `--only`; dry run unless `--apply`):
  - Emptied Local locations / Local farm info versions are rebuilt with `LocationsModuleBuilder` / `FarmInfoModuleBuilder`; other Local versions are reported only.
  - Emptied cloned versions: the team's forms are switched back to the global version at the same position and the copy is deleted (skipped, with a warning, if the copy holds choice entries added by the team). The Informed Consent / HDDS page clones again when the team next opens it.
  - Forms missing a module of their template get it attached with the same insertion logic.
  - Every touched form is flagged `draft_needs_update`.

## Tests
- Package: `XlsformTemplateImportScopeTest`, `XlsformSyncInsertOrderTest` (6 cases), and a new case in `PrepareSurveyRowPathsTest`.
- Host: `tests/Feature/ReplaceXlsformKeepsTeamVersionsTest.php` (real v1 to v2 import with fixtures `tests/assets/replace-xlsform-v1.xlsx` / `-v2.xlsx`, a team clone, a team Local version, a same-named team row, a team-owned choice entry, an empty list of another template, a team form), and an R3 block in `tests/Feature/Console/RepairFormDeployIssuesTest.php`.
- Each new test group was checked to fail when the fix is reverted, except the host path test, which the old flow masks (it deleted the copies first); the package `PrepareSurveyRowPaths` test covers that case.
- Results: host suite 380 passed. Package suite 213 passed and 24 failed; all 24 failures are `Class "...Tests\Database\Factories\TeamFactory" not found` (the directory is `tests/database/Factories`, lowercase, which fails on case-sensitive file systems) and are not caused by this change. PHPStan reports no new errors in the changed files.

## Not changed (decisions)
- A team's cloned version stays a snapshot of the global version at the time it was cloned; new questions in the new file do not reach it (accepted by Dan, 2026-11-09).
- Design 4 (never swap in an empty Local version) is not implemented; kept as a nice to have.
- Existing module positions are not touched when the new file moves a module.
- The Initial Pilot page keeps its loading state after a failed deployment (`failed()` uses `updateQuietly`), and two web requests ran out of PHP-FPM memory during the local reproduction. Both are unrelated and not addressed.

## Verified locally (2026-11-09)
Restored the pre-Replace dump (and put the three old template files back on disk, since a dump does not contain media files), replaced all three templates with the latest files, then used Initial Pilot: no failed jobs, no emptied team versions, no form missing a module (form 1 order: metadata, introduction, locations, farm info), all three drafts created on ODK Central, and the `path` check returned 0 rows.

## Still to do
- Run R3 on the damaged local database (dry run first) and on staging; run the pre-implementation SQL on staging and production first.
- Commit in the submodule, then bump the submodule pointer here.
