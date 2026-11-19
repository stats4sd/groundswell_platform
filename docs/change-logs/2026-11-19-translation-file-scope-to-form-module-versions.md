# Change log: limit the translation file to the module versions the team's form uses (2026-11-19)

Plan: [2026-11-19-translation-file-scope-to-form-module-versions.md](../plans/2026-11-19-translation-file-scope-to-form-module-versions.md)

The code change is in the `packages/filament-odk-link` submodule; this repo bumps the submodule and holds the tests, the plan and this log.

## Problem

The translation file a team downloads from Survey Translations listed the rows of every module version of the template. A question could appear twice (the global version plus a team's clone), and a team saw rows from other teams' cloned versions and from `Local {module}` versions its form does not select.

## Change

- `XlsformTemplate` gets `selectedModuleVersionIdsFor()`, `surveyRowsForTranslation()` and `choiceListEntriesForTranslation()`.
  - Row set = the versions selected by the owner's form(s) for the template, intersected with the template's own modules. Versions with no module id (the `Local Context` custom-indicator version) stay out, as before.
  - With no owner, no form, or a form with no selected versions, the file falls back to the default versions, so it is never empty.
  - Choice entries keep the owner scope (own plus global), written in the query rather than via the tenant global scope, and leave out entries the owner removed from context (`notRemovedFor`), as the generated form does.
- `XlsformTemplateTranslationsExport::collection()` uses these and builds its rows once per export (`styles()` calls it again for the row count).

The import side (`TranslationUploadInspector`, `XlsformTemplateLanguageImport`) is unchanged: it still accepts any row id of the template, so files downloaded before this change still import. Rows of other teams' versions in an old file are still written as before.

## Tests

New `tests/Feature/SurveyLanguages/TranslationExportScopeTest.php` (10 tests): clone rows only, global rows only, other teams' clones and unselected local versions staying out, a selected version with no module id staying out, the fallbacks (no owner, form with no selection, team without a form), removed choice entries (also a same-named entry of another list), an old-style file still passing the upload check, and the collection being built once. Local: main 410 passed, submodule 268 passed.

## Verified on staging (2026-11-19, team 6, Farmer Registration)

- The form selects versions 2, 3, 5 (global) and 115 (`Local locations`, owned by team 6).
- New file: 24 survey lines (17 entry ids) and 43 choice entries, all inside the selected versions; no duplicate name and translation type; no row from another team's version.
- Files downloaded before the deployment: 43 survey lines (31 ids), 14 ids outside the selected versions, 12 duplicated name and translation type pairs.
- Upload: four edited cells (survey label, required message, a choice label) landed on the rows the form uses; the redeployed draft in ODK Central and Enketo showed them, and re-uploading the unedited file restored the original text.
- Old-style file with ids of versions the form does not select: accepted and imported. A first old file was rejected because 36 of its choice entries (the `Local locations` options, ids 2830 to 2865) no longer exist after that version was rebuilt; the validation is unchanged, so that rejection is not caused by this change.
- The three test forms deployed and opened in Enketo.

## Not done

- Strings already written onto other teams' versions by the old behaviour are not cleaned up.
- Skipping rows of other teams' versions on import is deferred.
- Same-named choice entries across modules: `docs/issues/2026-11-19-translation-file-duplicate-choice-entries.md` (to be discussed).
