# Change log: ODK form deploy fixes, Phase 1

Implements items 1-5 of [docs/plans/2026-10-26-odk-form-deploy-fixes.md](../plans/2026-10-26-odk-form-deploy-fixes.md). All code changes are in the `packages/filament-odk-link` submodule (branch `fix-issue-20261026`), committed in two commits (the follow-up below is a third, not yet committed at the time of writing). Phase 2 (item 6) is not implemented.

## Changes

| # | File | Change |
|---|---|---|
| 1 | `src/Jobs/AddMissingChoiceListStrings.php` | Source lists now exclude the list being filled. Each unlabelled entry is matched against all other same-named lists by `name`, `properties`, `cascade_filter` and `owner_id`, and only an entry that actually has strings is used. |
| 2 | `src/Exports/XlsformExport/XlsformChoicesExport.php` | The grouped row's `id` is the highest id among entries that have language strings, falling back to `max(id)`. |
| 3 | `src/Models/OdkLink/Xlsform.php` | `localiseModules()` looks up the team's local version with `xlsform_module_id` as well as owner and name. |
| 4 | `src/Imports/XlsformTemplate/XlsformModuleImport.php` | `can_be_extended` / `can_be_replaced` are only written when the file has a `localisable_module` column (present, even if blank, means the file is authoritative). Without the column existing flags are kept and new modules get the column default (`false`). |
| 5 | `src/Exports/XlsformExport/XlsformChoicesExport.php` | Entries are ordered by `list_name`, then by the group's lowest id (template order), instead of alphabetically by name. |
| 5b | `src/Exports/XlsformExport/XlsformChoicesExport.php` | Follow-up: team-owned entries are ordered after the global ones (then by lowest id). Needed because a template re-upload recreates the global entries with higher ids than team entries added earlier; seen in the local end-to-end test, where team crops 41-43 were exported before global crops 1-35. |

## Tests

New or extended (each fails on the previous code and passes now, checked by stashing the source change):

- `tests/Unit/Jobs/AddMissingChoiceListStringsTest.php`: self-as-source case (`crop_list`), owner mismatch.
- `tests/Unit/Exports/XlsformChoicesExportTest.php` (new): labelled representative, all-unlabelled fallback, template order with a team-added entry last, team entries created before the global ones still last (5b).
- `tests/Unit/Models/XlsformLocaliseModulesTest.php` (new): own-module lookup across templates, non-replaceable module left alone.
- `tests/Unit/Imports/XlsformModuleImportTest.php`: flags kept without the column, flags set with it, blank cell in a spreadsheet-style row.
- `tests/TestCase.php`: registers `Kirschbaum\PowerJoins\PowerJoinsServiceProvider` so the export's `leftJoinRelationship()` resolves in the isolated test app.

Package suite: 172 passed, 24 failed (171 before item 5b). The 24 failures are identical to the run before the change (all `Class "Stats4sd\FilamentOdkLink\Tests\Database\Factories\TeamFactory" not found`: the `tests/database/` directory name does not match the `Database` namespace on a case-sensitive filesystem). `phpstan` reports the same 133 errors before and after. `pint` flags pre-existing style in the touched source files; the new test files are clean and the existing files were not reformatted.

## Checked against the staging clone (read-only)

Running the new export for team 3's Global Indicators form (form 8): 503 choice rows, none without a label (before: `crop_list` 1-35 had none); `crop_list` 45 rows all labelled; `rapport` exports as `easy > medium > difficult > very_difficult > no_answer` and `reliability` as `5 > 4 > 3 > 2 > 1`. Form 9 (Women's) shows the same orders. This confirms items 2 and 5 on real data without running the data repair; the Contextualise page still needs repair R1 to show the labels.

## Not done yet

See the plan's "Rollout order": commit and deploy (submodule pointer bump, `php artisan queue:restart`), data repair R1/R2, the `can_be_replaced` SQL, then redeploy team 3's drafts. Phase 2 follows after that.

## End-to-end verification on a clean local DB

Clean local DB pointed at its own ODK Central; the three master xlsx uploaded; `can_be_replaced` set to 1 for the five `locations` / `farm info` modules by SQL.

- **Item 4:** re-uploading all three xlsx (via Replace XLSForm on each template's View page) left the five flags at 1 and `new farm info` at 0. Before the change this reset them to 0.
- **Items 1, 2:** three team crops (41-43, with labels) were added in the front end, then the Global Indicators xlsx was re-uploaded; both `crop_list` copies ended with 35/35 labelled global entries. The downloaded form's `choices` sheet has `crop_list` with 0 unlabelled rows.
- **Item 3:** after importing locations and farms with the combined wizard, flagging the team's forms for update and opening Initial Pilot, the team's forms use `Local locations` / `Local farm info`. The Enketo preview of Global Indicators shows `Cluster 1`, `Cluster 2`, the groups and the imported farms (F001-F003) instead of the `loc_1_x` placeholders.
- **Item 5:** the downloaded Global Indicators and Women's forms list `rapport` as easy > medium > difficult > very_difficult > no_answer and `reliability` as 5 > 4 > 3 > 2 > 1. `crop_list` showed team crops 41-43 first, which led to 5b.
- Both downloaded forms have balanced groups (no stray `end group`) and contain the location and `Farm_Summary` questions.

## Known follow-ups found during testing

- The list-row **Replace XLSForm** action crashes (`modalContent()` returns an array); use the action on the template View page until it is fixed.
- Farm choices appear newest first (F003, F002, F001), as returned by ODK Central.
