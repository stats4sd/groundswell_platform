# Change log: ODK form deploy fixes, Phase 1

Implements items 1-5 of [docs/plans/2026-10-26-odk-form-deploy-fixes.md](../plans/2026-10-26-odk-form-deploy-fixes.md). All code changes are in the `packages/filament-odk-link` submodule (branch `fix-issue-20261026`), uncommitted at the time of writing. Phase 2 (item 6) is not implemented.

## Changes

| # | File | Change |
|---|---|---|
| 1 | `src/Jobs/AddMissingChoiceListStrings.php` | Source lists now exclude the list being filled. Each unlabelled entry is matched against all other same-named lists by `name`, `properties`, `cascade_filter` and `owner_id`, and only an entry that actually has strings is used. |
| 2 | `src/Exports/XlsformExport/XlsformChoicesExport.php` | The grouped row's `id` is the highest id among entries that have language strings, falling back to `max(id)`. |
| 3 | `src/Models/OdkLink/Xlsform.php` | `localiseModules()` looks up the team's local version with `xlsform_module_id` as well as owner and name. |
| 4 | `src/Imports/XlsformTemplate/XlsformModuleImport.php` | `can_be_extended` / `can_be_replaced` are only written when the file has a `localisable_module` column (present, even if blank, means the file is authoritative). Without the column existing flags are kept and new modules get the column default (`false`). |
| 5 | `src/Exports/XlsformExport/XlsformChoicesExport.php` | Entries are ordered by `list_name`, then by the group's lowest id (template order), instead of alphabetically by name. |

## Tests

New or extended (each fails on the previous code and passes now, checked by stashing the source change):

- `tests/Unit/Jobs/AddMissingChoiceListStringsTest.php`: self-as-source case (`crop_list`), owner mismatch.
- `tests/Unit/Exports/XlsformChoicesExportTest.php` (new): labelled representative, all-unlabelled fallback, template order with a team-added entry last.
- `tests/Unit/Models/XlsformLocaliseModulesTest.php` (new): own-module lookup across templates, non-replaceable module left alone.
- `tests/Unit/Imports/XlsformModuleImportTest.php`: flags kept without the column, flags set with it, blank cell in a spreadsheet-style row.
- `tests/TestCase.php`: registers `Kirschbaum\PowerJoins\PowerJoinsServiceProvider` so the export's `leftJoinRelationship()` resolves in the isolated test app.

Package suite: 171 passed, 24 failed. The 24 failures are identical to the run before the change (all `Class "Stats4sd\FilamentOdkLink\Tests\Database\Factories\TeamFactory" not found`: the `tests/database/` directory name does not match the `Database` namespace on a case-sensitive filesystem). `phpstan` reports the same 133 errors before and after. `pint` flags pre-existing style in the touched source files; the new test files are clean and the existing files were not reformatted.

## Checked against the staging clone (read-only)

Running the new export for team 3's Global Indicators form (form 8): 503 choice rows, none without a label (before: `crop_list` 1-35 had none); `crop_list` 45 rows all labelled; `rapport` exports as `easy > medium > difficult > very_difficult > no_answer` and `reliability` as `5 > 4 > 3 > 2 > 1`. Form 9 (Women's) shows the same orders. This confirms items 2 and 5 on real data without running the data repair; the Contextualise page still needs repair R1 to show the labels.

## Not done yet

See the plan's "Rollout order": commit and deploy (submodule pointer bump, `php artisan queue:restart`), data repair R1/R2, the `can_be_replaced` SQL, then redeploy team 3's drafts. Phase 2 follows after that.
