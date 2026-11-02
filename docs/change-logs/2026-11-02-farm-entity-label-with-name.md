# Change log: optional farmer name in the ODK farm entity label

Implements [the plan](../plans/2026-10-29-farm-entity-label-with-name.md), 2026-11-02.

Farm entities on ODK Central can now be labelled `ID: 154 / Name: Elvia Vasquez` (what ODK Collect and Central show, including the farm picker in survey forms) instead of just the farm code. It is opt-in per team; teams that do not pick a name column are unchanged.

## What changed

| Area | Change |
|---|---|
| Import forms (`ImportFarmsAction`, `ImportLocationsAndFarmEntities`) | New optional "Which column contains the farmer name?" select. The chosen column is hidden from the identifier/property lists. |
| `FarmEntityImport` | Resolves `farm_name_column`, passes each row's `name` and the column header to `bulkCreateFarms()`, and drops the column from the generic identifier/property lists if it was also ticked. |
| `OdkFarmEntityService` | `buildFarmLabel()` (the only place that knows the format; trims and collapses whitespace; blank name falls back to the code). `bulkCreateFarms()` takes `$nameColumn`: stores the name as an `identifier`, labels rows, and saves the sanitized property name on the team. `createFarm()`/`updateFarm()` label from the team's `farm_name_property`. |
| `syncLocalFarm()` | For teams with `farm_name_property` set, `team_code` is read from the entity's `team_code` property (label as fallback) in **both** the adopt and the existing-farm branch. Without this, every refresh would have overwritten each farm's `team_code` with the composite label. Teams without the setting keep the old label-follows-team_code behaviour. |
| `teams.farm_name_property` | New nullable column (migration `2026_11_02_000001`). |
| `odk:backfill-farm-labels` | One-time command to relabel existing farms. Dry run by default, `--apply` writes; `--team=*`, `--name-property=`. Reads the live Central feed, skips farms with no name or an already-correct label, reports per-farm failures and continues, safe to re-run. |

## Behaviour to know

- Re-importing a file does not relabel farms that already exist (rows with an existing `team_code` are skipped); use the backfill command.
- For a team with the setting, editing a farm's label directly in Central no longer updates `team_code`; change the code in the platform instead.
- A farm whose name is cleared in the Edit form goes back to a code-only label.

## Tests

New: `tests/Feature/Services/OdkFarmEntityLabelTest.php` (label builder, bulk import, create/update, sync, relabel), `tests/Feature/Imports/FarmEntityImportNameColumnTest.php`, `tests/Feature/Console/BackfillFarmLabelsTest.php`. Full suite: 367 passed.

## Not yet verified

The backfill takes each entity's `baseVersion` from `__system.version` in the OData feed, which could not be checked against a live Central. If absent it fetches the version per entity. Check on staging with a dry run, then `--apply`.
