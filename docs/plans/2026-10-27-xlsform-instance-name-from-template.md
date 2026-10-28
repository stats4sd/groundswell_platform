# Plan: Keep `instance_name` and `default_language` from the template's `settings` sheet

**Status: In Progress**

Done (2026-10-28): migration `035` (package and host), `syncSettingsFromFile()` on upload, `XlsformInstanceNameResolver` and the settings export, the `odk:backfill-template-settings` command, and their tests. Tested on a local DB against a separate ODK Central: the template expression reaches the ODK Central XForm (`instanceName` bind) and submissions get the expected name (Farm Registration: `REGISTRATION:<interviewer> - <participant> - <date>`); a form missing a referenced question falls back to `"instance"` with a warning. Left to do: deploy to staging (backup, migrate, `queue:restart`, backfill dry run then `--apply`, redeploy one team first), then write the change log and set this plan to Completed. A stale queue worker still running old code also produces `"instance"`; restart it after every deploy.

All package changes are in the `packages/filament-odk-link` submodule, on a new branch cut from `staging-hotfix-20261026` (old layout, `src/` at the repository root; staging runs `80b95d2`). Do **not** branch from `dev` (colleague's monorepo restructure in progress, see "Submodule branching" in [2026-10-26-odk-form-deploy-fixes.md](2026-10-26-odk-form-deploy-fixes.md)). Branch: `instance-name-hotfix-20261027`. Delivery is a submodule commit plus a pointer bump here, as in the previous hotfix.

## Problem

`XlsformSettingsExport::collection()` hard-codes `'instance_name' => '"instance"'` (with a `// TODO: fix`), so every deployed form gives every submission the same name in ODK Collect. The template's `settings` sheet is never imported: nothing under `src/Imports/XlsformTemplate/` reads it, and `XlsformTemplate::syncEntityListsFromFile()` is the only code that re-reads the stored xlsx (it already lists `settings` among the sheets of the generic `XlsImport`, but only `entities` is used). `default_language` (e.g. `English (en)`) is dropped the same way.

Master templates carry expressions such as (to be confirmed against the stored xlsx in step 0):

- Farm Registration: `concat("REGISTRATION:", ${interviewer_name}, ' - ', ${participant_name}, ...)`
- Global Indicators: `concat("GLOBAL:", ${interviewer_name}, ' - ', ${farm_var_participant_name}, ...)`
- Women's: `concat("WOMEN'S FORM:", ${interviewername}, ' - ', ${respondentname_woman}, ' - ', ${starttime_auto})`

## Verified facts that shape the design

- `farm_var_*` rows are **not** in the master template. They are created per team by `App\Services\XlsformModules\FarmInfoModuleBuilder::populate()` (prefix `farm_var_`, one `calculate` row per dataset variable, inside the `farms` group) in the team's `Local farm info` module version. `Xlsform::localiseModules()` swaps that local version in for the global `farm info` version when `can_be_replaced` is true. So `${farm_var_participant_name}` exists in a team's Global Indicators form only if (a) the local farm info version has been built and (b) the form has actually been switched to it. A template-level check would be wrong; the check must run against the **form's** final survey rows.
- `FarmInfoModuleBuilder` only guarantees `participant_name`, `participant_sex`, `participant_age` as identifiers, so `farm_var_participant_name` should normally exist once built. The fallback is for teams where it has not been built or the module was not swapped.
- The settings sheet is exported by `XlsformWorkbookExport::sheets()` for each `Xlsform` (not the template), so the export has `$this->xlsform->xlsformTemplate` available. `Xlsform::surveyRows()` (hasManyDeep via `selected_xlsform_module_versions`) gives the rows the export will use.
- Package migrations are registered by numeric prefix in `FilamentOdkLinkServiceProvider::getMigrations()` (last is `034_create_templates_entity_lists_table`). The host app holds a byte-identical copy of each in `database/migrations/` named `<timestamp>_<NNN>_<name>.php` (checked for 034: `2026_06_30_133317_034_create_templates_entity_lists_table.php`).
- Re-uploading a template currently wipes team-cloned module versions, so it must not be the backfill path.

## Design

### 1. Migration (package and host)

| Package (`database/migrations/`) | Host (`database/migrations/`) | Change |
|---|---|---|
| `035_add_settings_columns_to_xlsform_templates_table.php` | `2026_10_28_000001_035_add_settings_columns_to_xlsform_templates_table.php` | `text('instance_name')->nullable()` and `string('default_language')->nullable()` on `xlsform_templates` |

- One migration for the one table (changed 2026-10-28 from two separate migrations; `035` was only committed locally, never pushed or run, so it was rewritten in place and renamed).
- Both nullable, so existing rows and the old default keep working before the backfill runs.
- Add the name to `getMigrations()`. `down()` drops both columns.
- `text`, not `string`, for `instance_name`: expressions can be long.
- Extend `tests/Smoke/MigrationsTest.php` with column checks (see Tests).

### 2. Read the settings sheet on upload

- New `XlsformTemplateSettingsImport` reads only the `settings` sheet (`SkipsUnknownSheets`). The generic `XlsImport` is not used: it throws `SheetNotFoundException` when `entities` (or any of its four sheets) is missing, and it loads the whole survey sheet just to read one row.
- `XlsformTemplate::readSettingsFromFile(?string $filePath = null)` returns `['instance_name' => ?string, 'default_language' => ?string]`, or `null` when there is no file or no `settings` sheet. Blank cells and missing columns give `null`; columns are matched by heading, not position.
- `XlsformTemplate::syncSettingsFromFile(?string $filePath = null)` writes both columns with `updateQuietly()`. A file with a `settings` sheet is the source of truth (columns it lacks are cleared); a file without a `settings` sheet leaves the stored values alone.
- Called from `HandleXlsformTemplateAdded::handle()` next to `syncEntityListsFromFile()`, i.e. when the media file is added or replaced. Not from `afterXlsformFileUpdated()` (changed 2026-10-28): that also runs on draft updates when the file has not changed.
- The backfill command reuses `readSettingsFromFile()`.
- Do not add `instance_name` to the template Filament form: it comes from the file only.

### 3. Use the values in the export

- `XlsformSettingsExport::collection()`:
  - `instance_name`: `InstanceNameResolver` result, or `'"instance"'` fallback.
  - `default_language`: template value, only if it matches a `label::<language>` column that the exported survey actually has (otherwise ODK Central rejects the form); else omit the column. Add `default_language` to `headings()` conditionally so heading and row stay aligned.
- New small class `Stats4sd\FilamentOdkLink\Services\XlsformInstanceNameResolver` (kept out of the export so it is unit-testable):
  1. Take `$xlsform->xlsformTemplate->instance_name`; if null/blank, return the default.
  2. Extract references with `/\$\{([^}]+)\}/`.
  3. Compare against the distinct `name` values of `$xlsform->surveyRows` (final rows, after `localiseModules()`), excluding rows of type `end_group` / `end_repeat` / `begin_*` with generated names if that causes false matches.
  4. If any referenced name is missing: `Log::warning` with xlsform id, template id, missing names; return the default.
  5. Not implemented (decided 2026-10-28): rejecting references to rows inside a `begin_repeat`. Step 0 showed that none of the three master expressions refers to a row in a repeat (all are in `begin_group`s), and how ODK Central treats such a reference is unverified. Revisit if a template needs it.
- `default_language` is checked in the export against the form's `locale_list` (`"<language name> (<iso_alpha2>)"`, the same format as the `label::` headers). No match: warning logged and the column left out.
- Settings are computed once per export instance (`settings()`), so `collection()` and `headings()` always agree and the warnings are logged once.
- The export row order and the `allow_choice_duplicates` column stay as they are.

### 4. Backfill command (host app)

`app/Console/Commands/BackfillTemplateSettings.php`, signature `odk:backfill-template-settings {--apply}`, same style as `RepairFormDeployIssues` (dry run by default, a comment line saying which mode).

- For each `XlsformTemplate` (by id): print `Template <id> (<title>)`, call `readSettingsFromFile()` (the same code the upload path uses, read-only) and print a table `column | current | from file | change/unchanged` for both columns.
- With `--apply`: `updateQuietly()` the two columns of the templates that differ. Idempotent: a second run reports everything as unchanged.
- Never touches module versions, survey rows or choices.
- A template with no stored xlsx or no `settings` sheet is reported as "skipped" and keeps its stored values.
- A file that cannot be read is reported and counted as failed; the run continues with the other templates and exits with a failure code at the end.
- Final line: `N template(s): X changed|would be changed, Y unchanged, Z skipped, W failed.`
- The host copy of migration `035` is created in the same commit as the command (the host test database is built from `database/migrations/`), so the migration copy is no longer part of the last commit.
- Dry run on a staging-DB clone first, as for the previous repair command (record the result in a change log).

## Rollout (staging)

0. **Before coding**: open the three stored xlsx (staging clone / `20261026 Latest ODK Forms`) and record the exact `settings` rows. Confirm in the Global Indicators master where `farm_var_participant_name` comes from (builder-created only, or also present in the master `farm info` module), and whether the other referenced names (`interviewer_name`, `participant_name`, `interviewername`, `respondentname_woman`, `starttime_auto`) are plain top-level rows. Check with ODK Central that `instance_name` may reference a `calculate` inside a group.
1. Implement in the submodule branch; run the package suite (`./vendor/bin/pest` in the package).
2. Commit, bump the pointer here, copy the two migrations into `database/migrations/`, add the command, host tests.
3. Deploy to staging: `php artisan migrate`, then `php artisan queue:restart` (long-running `queue:work` keeps old code).
4. `php artisan odk:backfill-template-settings` (dry run), review the table, then `--apply`.
5. Regenerate and deploy one test form per template (draft); open the downloaded xlsx and the draft in Collect/Enketo; check the submission name and the logs for fallback warnings.
6. Check `storage/logs/laravel.log` for the new warning after deploying a team form that has no local farm info (expected fallback), to confirm the safety net works.

## Forms that are already deployed

- `instance_name` is part of the form definition, so existing deployed forms keep `"instance"` until a **new version** is generated and published. The backfill alone changes nothing on ODK Central.
- Existing submissions keep their old names; this is not retroactive.
- After the backfill, each deployed form needs: regenerate xlsx -> deploy draft -> publish (the existing `Xlsform::deployDraft()` / `publishForm()` flow or `UpdateXlsformDrafts`). Publishing creates a new form version; field users must refresh the form in Collect to pick it up (in-flight drafts finish on the old version).
- Do **not** bulk-redeploy automatically. Redeploy Guatemala - ARGIDIVI first, verify, then the second team (see Decisions).

## Tests

Package (Pest + Testbench, extend existing files):

- `tests/Smoke/MigrationsTest.php`: `xlsform_templates` has `instance_name` and `default_language`.
- `tests/Unit/Exports/XlsformSettingsExportTest.php` (currently uses an unsaved `Xlsform`; the new cases need a saved template and survey rows): template without `instance_name` -> `'"instance"'`; template with an expression whose variables all exist -> expression used; one missing variable -> default and a warning logged (`Log::spy()`); `default_language` used only when it matches a label column; headings stay aligned with the row.
- New `tests/Unit/Services/XlsformInstanceNameResolverTest.php`: no references (literal), several references, repeated reference, variable only in a team-local module version, variable inside a repeat.
- New `tests/Unit/Models/XlsformTemplateSettingsSyncTest.php`: build a small xlsx with a `settings` sheet (fixture under `tests/assets` or built in the test) -> `syncSettingsFromFile()` fills both columns; missing sheet / blank cell -> null; does not touch module versions or survey rows.

Host app:

- Feature test for `odk:backfill-template-settings`: dry run writes nothing and prints the changes; `--apply` writes; second `--apply` is a no-op; template without file is skipped.
- Existing suites (`./vendor/bin/pest`, `./vendor/bin/phpstan analyse`, `./vendor/bin/pint`) must stay green.

## Risks

- **Invalid expression makes the form fail to deploy**: mitigated by the variable check and the fallback; the repeat-scope and group-scope behaviour must be verified against ODK Central (step 0) because pyxform errors surface only at deploy time.
- **Referenced variable missing for some teams** (farm info not built / not swapped): falls back to `"instance"` with a warning; the form still deploys. Teams silently get the old name, so the warning log is the only signal; consider a notification later (out of scope).
- **`default_language` mismatch**: a value that does not match an exported label column fails pyxform; hence the match check and omission.
- **Heading-row parsing**: `XlsImport` uses `WithHeadingRow`; headings are slugged (`instance_name`, `default_language`). Confirm the real sheets use those exact header names.
- **Re-upload wipes team-cloned module versions** (known, separate work): this plan does not make it worse and deliberately avoids depending on re-upload.
- **Old-layout branch**: the change has to be ported when the monorepo restructure lands (same cherry-pick approach as in the previous plan).

## Porting to the monorepo `dev` later

The submodule commits are on branch `instance-name-hotfix-20261027` (old layout), no PR to the submodule `dev`. When the restructure settles, cherry-pick `f7f5fe5 6ad9f3c f9fc720` on top of the commits listed in "Submodule branching" of [2026-10-26-odk-form-deploy-fixes.md](2026-10-26-odk-form-deploy-fixes.md). Keep the branch on the remote so the pinned commit stays fetchable.

## Out of scope

- Empty `Local farm info` module versions breaking Global Indicators/Women's deploys: [issue](../issues/2026-10-28-empty-local-farm-info-module-version.md).
- Fixing the re-upload data loss; a UI to edit `instance_name`; other `settings` columns (`version`, `form_id`, `form_title` stay generated); bulk redeploy of existing forms; notifying teams about the fallback.

## Decisions (agreed with Dan, 2026-10-27)

- Branch `instance-name-hotfix-20261027` already exists in the main repo and in the submodule (cut from `staging-hotfix-20261026`).
- Separate commits, each passing tests on its own: (1) migration 035 (both columns), (2) `syncSettingsFromFile()` + read on upload, (3) resolver + settings export, (4) backfill command + host migration copy, (5) submodule pointer bump and change log.
- Already-deployed forms: redeploy **one team first, Guatemala - ARGIDIVI (team 3)**, check the result in ODK Central/Collect, then the second team. Reason: a rejected expression then only affects one team, and this team can exercise the `farm_var_*` check.
- Backfill is by the command only (option A). `generateXlsfile()` does **not** self-heal null `instance_name`/`default_language`; the rollout order (migrate, backfill, then redeploy) avoids a gap.

## Status of work

Not started. Implementation begins 2026-10-28, starting with step 0 of the rollout (inspect the three stored xlsx settings sheets).
