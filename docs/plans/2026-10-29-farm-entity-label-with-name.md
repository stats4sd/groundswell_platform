# Plan: Optional farmer name in the ODK farm entity label

**Status: Not Started**

## Background

Farms are stored as entities in an ODK Central Entity List. The entity `label` is what ODK Collect and ODK Central show in their lists. Today the label is always the farm code (`team_code`), so enumerators see a bare code such as `154`.

We want the label to read `ID: 154 / Name: Elvia Vasquez` when the team has a farmer name available, and to keep today's behaviour (label = farm code) otherwise.

### How the label is set today

| Path | Code | Label sent to Central |
|---|---|---|
| Excel/CSV import | `OdkFarmEntityService::bulkCreateFarms()` (`'label' => $row['teamCode']`) | `team_code` |
| Manual create | `createFarm()` → `OdkLinkService::createOdkEntity(..., $teamCode, ...)` | `team_code` |
| Manual edit | `updateFarm()` → `updateOdkEntity(..., $teamCode, ...)` | `team_code` |
| Sync from Central | `syncLocalFarm()` | reads label **back** as `team_code` when adopting a new entity |

The last row is the catch: `syncLocalFarm()` assumes label == team_code (comment at `OdkFarmEntityService.php` ~L1006). If we only change what is pushed, every later sync that adopts a new entity would store the whole `ID: ... / Name: ...` string as its `team_code`. The sync must be fixed in the same change.

## Decisions (agreed with Dan, 2026-10-29)

1. **Name source:** a new **optional** column selector in the import form ("Farmer name column"). Not guessed from header names.
2. **Format is not user-configurable.**
   - Name column chosen → label is `ID: {team_code} / Name: {name}`.
   - No name column chosen → label is `{team_code}` (current behaviour, unchanged).
   - A row whose name cell is empty falls back to `{team_code}` for that row.
3. **Existing farms** are backfilled with a one-time Artisan command.

## Design

### Where the "name property" is remembered

`updateFarm()` and `createFarm()` (manual forms) also push a label, so they need to know which entity property holds the name. Import-form choices are not kept anywhere today, so we need to persist one setting per team.

- Add a nullable column `farm_name_property` to `teams` (host migration), holding the **sanitized ODK property name** (the key in the entity data, as produced by `reconcileProperties()`), not the spreadsheet header.
- Set by the import when a name column is chosen; null means "label = team_code".
- If a later import chooses a different name column from the stored one, the stored value is overwritten and the user is told in the import form helper text that existing labels are not changed until they run the backfill command (or the farms are edited).

> Alternative considered: store it on the `Dataset`. Rejected for now because the dataset is created lazily in `ensureDataset()` and `Team` already owns the other per-team ODK settings. Revisit if the package should own this.

### Label builder

Add one public method on `OdkFarmEntityService`:

```php
public function buildFarmLabel(string $teamCode, ?string $name): string
```

Returns `"ID: {$teamCode} / Name: {$name}"` when `$name` is non-blank after trimming, otherwise `$teamCode`. It is the only place that knows the format.

### Name column in stored data

The name column is also stored as an ordinary entity property (so the Edit form shows it and the backfill can read it from Central). It is excluded from the identifier/property checkbox lists (like the farm code column) and added to the properties automatically by the import. The property is registered through the normal `reconcileProperties()` path.

### Sync fix (`syncLocalFarm()`)

When adopting an entity that does not exist locally, read `team_code` from the entity's **`team_code` property** (already pushed by `createFarm()`, `updateFarm()` and `bulkCreateFarms()`), and only fall back to the label, then the uuid, if the property is missing (entities registered directly in Enketo). This keeps existing behaviour for entities that have no `team_code` property and makes the new label format safe.

## Implementation steps

1. **Migration** (host app): add nullable `teams.farm_name_property`. Add to `Team` model if it needs a cast or fillable (models are globally unguarded, so likely nothing).
2. **Service**
   - Add `buildFarmLabel()`.
   - `bulkCreateFarms()`: accept `name` per row (new key in the prepared rows) and use `buildFarmLabel()` for `'label'`. Persist `farm_name_property` on the team when a name column was used.
   - `createFarm()` / `updateFarm()`: take the name from the identifiers/properties array using `$team->farm_name_property` and use `buildFarmLabel()`.
   - `syncLocalFarm()`: read `team_code` from the property first (see above).
3. **Import**
   - `FarmEntityImport::collection()`: resolve the optional `farm_name_column`, add `name` to each prepared row, and pull that column out of identifiers/properties if it was also ticked.
   - Both import forms get a new optional `Select::make('farm_name_column')` after `farm_code_column`, and the identifier/property lists hide the chosen name column (same `disableOptionWhen` pattern as farm code):
     - `app/Filament/App/Clusters/LocationLevels/Resources/FarmEntityResource/Pages/ImportLocationsAndFarmEntities.php`
     - `app/Filament/Tables/Actions/ImportFarmsAction.php`
   - Helper text explains the label format and that leaving it empty keeps the farm code as the label.
4. **Backfill command** `odk:backfill-farm-labels` (modelled on `app/Console/Commands/BackfillTemplateSettings.php`)
   - Options: `{--team=*}` (default all teams with an ODK project), `{--name-property=}` (sanitized property name; defaults to the team's `farm_name_property`), `{--dry-run}`.
   - For each team: fetch the live entities, compute the new label with `buildFarmLabel()`, and call `updateOdkEntity()` only when the label differs from Central's current one. Send the entity's existing data unchanged and the current `baseVersion`, then save the new `odk_version`.
   - Set `teams.farm_name_property` from `--name-property` if given and the team has none.
   - Idempotent: a second run changes nothing. Prints a per-team summary (updated / skipped / failed) and is safe to re-run after partial failure.
   - Run `--dry-run` first on staging and review the printed before/after labels.
5. **Docs:** add a short note to the farm import help text and a change log in `docs/change-logs`.

## Tests (Pest)

- `buildFarmLabel()`: name present, null, blank/whitespace, name containing `/`.
- Import with a name column → entities pushed with `ID: x / Name: y`; without → label is the code; row with empty name → falls back to the code.
- `teams.farm_name_property` is set by an import that uses a name column and left alone otherwise.
- `createFarm()` / `updateFarm()` use the stored property; editing the name changes the label.
- `syncLocalFarm()`: a label of `ID: 154 / Name: Elvia Vasquez` with a `team_code` property of `154` creates a farm with `team_code` `154`; with no property it falls back to the label (existing behaviour).
- Backfill command: dry run changes nothing; real run updates only mismatched labels; second run is a no-op.

## Risks and notes

- **Other consumers of the label.** Before implementing, search XLSForm templates and `FarmInfoModuleBuilder` for anything that matches entities on `label` (e.g. `instance_name` or a `pulldata`/entity-lookup calculation). The label is only a display value in ODK Entities, but this must be confirmed.
- **Entity update conflicts.** `updateOdkEntity()` needs the right `baseVersion`; the backfill must refresh the version from Central, not trust the local `odk_version`.
- **Mobile cache.** ODK Collect devices only pick up new labels after the next entity list sync.
- **Name column is optional on purpose.** Teams that do not hold farmer names are unaffected.

## Out of scope

- Per-team configurable label templates.
- Using a name column from the location (not farm) import.
