# A team's empty "Local farm info" module version replaces the global one and breaks form deployment

**Status**: Open — found on 2026-10-28 while testing [the instance_name plan](../plans/2026-10-27-xlsform-instance-name-from-template.md) on a local DB. The cause of the empty versions is not known.

## What happens

`Xlsform::localiseModules()` swaps the global `farm info` module version for the team's `Local farm info` version whenever one exists, without checking that it has any survey rows. If the local version is empty, the form loses every farm info question, including the `id` question (`select_one_from_file Farm_Summary.csv`) that the template's `entities` sheet refers to (`entity_id` = `${id}`), and the `farm_var_*` calculate rows.

Result for Global Indicators (and Women's) forms: ODK Central rejects the file and the deploy job fails with `XlsformValidationException: [row : 2] On the 'entities' sheet, the 'entity_id' value is invalid. Reference variables must contain a name of a question ...` (two rows in `failed_jobs`, 2026-10-28 11:50). The form keeps its old draft on ODK Central, so the failure is easy to miss.

## Evidence (local DB, 2026-10-28)

| xlsform | team | selected `farm info` version | survey rows |
|---|---|---|---|
| 4 | 3 | Local farm info (version 38) | 0 |
| 5 | 4 | Local farm info (version 43) | 0 |
| 6 | 5 | Global farm info (version 10) | 13, deploys fine |

Same team, different templates (local DB, team 3): the `Local farm info` version of the Global Indicators form (xlsform 4) has 0 survey rows while the one of the Women's form (xlsform 7) has 10. Every template has its own `farm info` module and `populate()` loops over all of them, so the empty version looks like a partial failure of that loop, not a team that never built farm info.

Forms 4 and 5 had deployed successfully the day before, so the swap to the empty version happened in between (the admin `Publish` action calls `localiseXlsforms()` and `localiseModules()`).

## Where to look

- `app/Models/Team.php` `localiseXlsforms()`: calls `FarmInfoModuleBuilder::populate()` only when `has_updated_locations` is true.
- `app/Services/XlsformModules/FarmInfoModuleBuilder.php` `populate()`: creates the `Local farm info` version with `firstOrCreate` before `ensureDataset()` and `buildSurveyRows()`, so a failure after that point leaves an empty version behind (not confirmed as the cause).
- `packages/filament-odk-link/src/Models/OdkLink/Xlsform.php` `localiseModules()`: swaps without checking the local version's rows.

## Suggested directions (not decided)

1. Make `localiseModules()` skip the swap when the local version has no survey rows, and log a warning.
2. Find out why the versions were left empty. Same team, same module name, one template populated and one not, so check the loop in `populate()` first (an exception after `firstOrCreate` for one module, or a variable-less dataset), then rows deleted later.
3. A repair for existing empty versions (re-run `populate()` for the team, or switch the form back to the global version), dry run first, like `odk:repair-form-deploy-issues`.
4. Check staging for the same state before relying on Global Indicators / Women's deployments: `SELECT` the `farm info` version of each form and its survey row count.

## Not related to

The `instance_name` change. With an empty farm info the export falls back to the default instance name and logs a warning; the deploy failure above is separate and has the same cause as the fallback.
