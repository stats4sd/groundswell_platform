# Plan: limit the translation file to the module versions the team's form uses

**Status: In Progress.** Code and tests are written and pass locally (main suite 410 passed, package suite 268 passed). Left: Dan's manual check with the before/after files, deployment to staging, the staging verification below, then the change log.

Branches: main repo (plan, change log, submodule bump); submodule `packages/filament-odk-link` (code and tests), PR base `staging-hotfix-20261026`.

## Problem

The translation file a team downloads from Survey Translations lists the rows of **every** module version of the template, so a question can appear twice and a team sees rows that its form does not use.

- `XlsformTemplateTranslationsExport::collection()` reads `$template->surveyRows` and `$template->choiceListEntries` (`XlsformTemplate`). These go template, module, all module versions, rows, with no owner or `is_default` filter.
- A team's form only uses the versions in `selected_xlsform_module_versions` (`Xlsform::surveyRows()` and `choiceListEntries()`).
- The extra versions come from `cloneForOwner` (first visit to the HDDS hints and Informed consent pages) and from `Local {module}` versions that carry a module id but are not selected.

Measured on the local database (team 3 has cloned versions):

| Template | Survey rows in the file | Used by the form | Choice entries in the file / used |
|---|---|---|---|
| Farm Registration | 44 | 29 | 54 / 44 |
| Global Indicators Form | 577 | 551 | 602 / 591 |
| Women's Form | 339 | 246 | 179 / 160 |

Effects:

1. The same question is listed twice (86 `name`+`type` pairs in the Women's Form).
2. A translator may fill the copy the form does not use, so the generated form has no translated text for it.
3. A team downloading the file sees the rows of another team's cloned version (93 rows for team 4 in the Women's Form), including text that team customised. Choice entries leak the same way: `cloneForOwner` keeps `owner_id` NULL on cloned global entries, so they pass the owner scope of every team (checked locally: all 8 entries of team 3's HDDS clone, 2 of 2 in its introduction clone).
4. Bigger files.

Not part of this problem: the same `list_name` existing in several modules (for example `crop_list` in `crops` and `crop_intensification`). Those repeats exist inside the set a form really uses, and form generation already treats same-named entries as one. Tracked separately in `docs/issues/2026-11-19-translation-file-duplicate-choice-entries.md`.

## Decision (agreed 2026-11-19)

Option A. The export lists only the rows of the module versions the owner's form uses for this template.

- Row set = the versions selected by the owner's `Xlsform` for the template, intersected with the versions that belong to one of the template's modules (`xlsform_module_id` in the template's modules).
- The intersection keeps today's behaviour for versions with no module id (the team's `Local Context` custom-indicator version): they stay out of the file.
- With no owner, an owner without a form for the template, or a form with no selected versions at all (never synced; the test fixture creates its form with model events off), fall back to the default (global) versions only, using the existing `surveyRowsForImport()` and `choiceListEntriesForImport()` scope. An empty file must never be the result.
- Implementation shape: take the selected version ids of the owner's form(s) for the template, then `$template->surveyRows()->whereIn('xlsform_module_versions.id', $ids)` (same for `choiceListEntries()`). Starting from the template relation gives the intersection with the template's modules for free.
- Find the owner's forms with `Xlsform::withoutGlobalScope('owned')` filtered by `owner_id` and `xlsform_template_id`, and use all of them (not `first()`), so the result does not depend on which tenant is current.
- Choice entries the owner "Removed from context" are left out, as in the generated form: apply `notRemovedFor($owner)` (agreed 2026-11-19), so the file matches the form.
- Choice entries must keep today's owner scope (own entries plus global ones). `XlsformChoicesExport` states it explicitly in its query; do the same instead of relying on the tenant global scope, which does not apply in queued or console contexts.
- The import side is **not** changed. `TranslationUploadInspector` and `XlsformTemplateLanguageImport` keep accepting any row id of the template, so files downloaded before this change still import exactly as today. Rows of other teams' versions in an old file are still written as today; whether to skip them is left for later.

## Fix

- Add one helper that returns the survey rows and choice entries in scope for an owner and a template, and use it in `XlsformTemplateTranslationsExport::collection()`. Place it where it can be reused (a method on `XlsformTemplate` taking the owner, or a small class in `Services`), not inline in the export.
- `styles()` calls `collection()` again for the row count; the helper result must be computed once per export so the two agree.
- Row ordering stays `sortBy('row_number')`, survey rows first, then choices.
- No migration, no data change. Callers are unchanged: both download actions and `copyTranslationFiles` (the generated file for a duplicated default locale) already pass `owner`.

## Tests

In the main repo, `tests/Feature/SurveyLanguages/TranslationExportTest.php` (the existing export tests and `TranslationFixture` live there; the package test suite has known factory/namespace failures). The submodule PR carries the code only; the main PR carries the tests and the submodule bump.

1. A team whose form uses a cloned version gets the clone's rows and none of the global version's rows for that module.
2. A team with no clone gets the global version's rows only.
3. Another team's cloned version never appears in the file.
4. A `Local {module}` version with a module id that the form does not select does not appear.
5. A selected version with no module id (`Local Context`) does not appear.
6. No owner, an owner without a form for the template, or a form with no selected versions: only default-version rows.
6b. A form that selects only some of the template's versions gets only those (the fixture form selects nothing today, so tests of the scoped case attach versions explicitly).
7. Choice entries follow the same scope, and an entry the owner removed from context is absent (also when only a same-named entry in another module's list was removed, as `notRemovedFor` defines it).
8. An old-style file containing ids of a non-selected version still passes `TranslationUploadInspector::validate` and imports (round trip).
9. Existing export, edit form and import tests keep passing without changed assertions. They rely on the default-version fallback, because the fixture's form selects no versions.

Then the full submodule and main suites, and `./vendor/bin/pint` on the changed files only.

## Verification on staging

1. Template of team 6: list the versions of its modules (`xlsform_module_versions` for the template's modules) and note any non-default or team-owned ones.
1b. Before deploying, check team 6's locales for strings stored on rows of a version its form does not select (see the first known limitation). Keep the files downloaded before the deployment.
2. Download "empty translation template" and "existing translations" for a team locale. Do not compare raw line counts (the file has one line per row and string type, and rows with no strings are absent); compare the set of `entry_id`s with the rows of the form's selected versions. No question appears twice, and no row comes from a version owned by another team.
3. Fill a few cells, upload, and confirm the strings land on the rows the generated form uses (redeploy the draft and check the xlsx from ODK Central).
4. Upload a file downloaded before the deployment: it is still accepted.

## Open questions

None open. The step 1b check on staging (team 6, 2026-11-19) found 12 (row, string type) gaps, all on builder-generated `Local locations` and `Local farm info` versions (`label` and `required_message` of `loc1`, `loc2`, `id` for one Spanish team locale), none on the HDDS clone and none on choice entries. Decision: no repair in this plan. Those versions are generated by module builders that write team-locale strings only partly, so the gaps already exist in the generated form today; after this change the translator simply sees those cells in the file, on the rows the form uses. The label gaps are already covered by the default-locale fallback (the selected rows carry the same text under the default Spanish locale); the 8 `required_message` cells have no Spanish text on the selected rows and stay blank until the translator fills them. The strings of that team locale found on rows of other teams' `Local locations` versions (owned by teams 1 and 3) are the cross-team writes described in Problem; they do not affect those teams' forms, and cleaning them up is not part of this plan.

## Known limitations

- A team whose form uses a clone, and which uploaded a translation under the old behaviour, may have strings on the global rows (the translator filled the first occurrence). After this change "Download existing translations" reads the clone's rows, so those cells look blank to the translator even though nothing was deleted. The form never used those strings either. Detect before deploying (step 1b); a one-off copy of the strings onto the selected rows is possible but not part of this plan.
- A file downloaded before a team first clones a module version, and uploaded after, writes its strings to the global rows while the form uses the clone. A remap on import (module, name, type to the selected row) could cover it; not done here.
- Skipping rows of other teams' versions on import is deferred.

## Out of scope

- De-duplicating same-named choice entries (separate issue).
- Changing the import validation or write behaviour.
- The reference-columns-look-editable issue (`docs/issues/2026-11-17-translation-file-reference-columns-look-editable.md`).
