# Plan: Optional farmer name in the ODK farm entity label

**Status: In Progress**

Implemented 2026-11-02 (all steps below, 367 tests passing); see [the change log](../change-logs/2026-11-02-farm-entity-label-with-name.md). Left to do: on staging, run `php artisan odk:backfill-farm-labels --team=<id> --name-property=<property>` as a dry run first and confirm Central's OData feed carries `__system.version` (otherwise the command falls back to one `getOdkEntity()` call per farm), then re-run with `--apply`. Mark Completed after that.

Revised 2026-11-02 after a line-by-line check of the plan against the code (see "Verification notes" at the end).

## Background

Farms are stored as entities in an ODK Central Entity List (`Farm_Summary`). The entity `label` is what ODK Collect and ODK Central show. It is also what the enumerator sees in the farm picker of every survey form: `FarmInfoModuleBuilder` builds the question as `select_one_from_file Farm_Summary.csv`, and Central serves that CSV with the entity label as the choice label. Today the label is always the farm code (`team_code`), so enumerators see a bare code such as `154`.

We want the label to read `ID: 154 / Name: Elvia Vasquez` when the team has a farmer name available, and to keep today's behaviour (label = farm code) otherwise.

### How the label is set and read today

| Path | Code | Label |
|---|---|---|
| Excel/CSV import | `OdkFarmEntityService::bulkCreateFarms()`, `'label' => $row['teamCode']` | pushed as `team_code` |
| Manual create | `createFarm()` → `createOdkEntity(..., $teamCode, ...)` | pushed as `team_code` |
| Manual edit | `updateFarm()` → `updateOdkEntity(..., $teamCode, ...)` | pushed as `team_code` |
| Sync, adopting an unknown entity | `syncLocalFarm()`, new-row branch | label is read **back** as `team_code` |
| Sync, existing farm | `syncLocalFarm()`, last block: `if ($label !== null && $farmEntity->team_code !== $label) update team_code = $label` | label is read **back** as `team_code` |

Both sync rows assume label == team_code. The second one runs for **every existing farm on every refresh**, so changing only what we push would overwrite every farm's local `team_code` with `ID: 154 / Name: ...` the next time the farm list loads. The sync must be fixed in the same change, in both branches.

## Decisions (agreed with Dan, 2026-10-29)

1. **Name source:** a new **optional** column selector in the import form ("Farmer name column"). Not guessed from header names.
2. **Format is not user-configurable.**
   - Name column chosen → label is `ID: {team_code} / Name: {name}`.
   - No name column chosen → label is `{team_code}` (current behaviour, unchanged).
   - A row whose name cell is empty falls back to `{team_code}` for that row.
3. **Existing farms** are backfilled with a one-time Artisan command.

## Design

### Where the "name property" is remembered

`createFarm()` / `updateFarm()` (manual forms) also push a label, so they need to know which entity property holds the name. Import-form choices are not kept anywhere today, so we persist one setting per team.

- Add a nullable column `farm_name_property` to `teams` (host migration), holding the **sanitized ODK property name**, i.e. the value `reconcileProperties()` returns for the name column, not the spreadsheet header. This matters: a header such as `name` is a banned ODK property name and is stored as e.g. `name_farm_entities_5`.
- Set by the import when a name column is chosen. Null means "label = team_code" everywhere.
- A later import that picks a different name column overwrites the setting. Helper text on the form says existing labels only change when the backfill command is run.
- An import with no name column leaves the setting alone and labels its rows with the farm code only.

> `ensureDataset()` is already one Dataset per team (`farm_entities_{team_id}`), so storing this on the Dataset would also work. A `teams` column is still preferred: `DatasetVariable.description` is the identifier/property tag and has already caused a stale-tag bug (see `docs/change-logs/farm-entities-simplify-and-gps-sync.md`), so it should not carry more meaning.

### Label builder

One public method on `OdkFarmEntityService`, the only place that knows the format:

```php
public function buildFarmLabel(string $teamCode, ?string $name): string
```

Trims the name and collapses internal whitespace/newlines to single spaces. Non-blank → `"ID: {$teamCode} / Name: {$name}"`, otherwise `$teamCode`.

### The name is stored as an identifier

The name column is stored as an ordinary entity property tagged **`identifier`**, not `property`. The import form already tells users that identifiers are the columns that must not be shared outside the project for data protection, and a farmer name is exactly that. The column is hidden from the identifier/property checkbox lists (like the farm code column) and added to the identifiers by `FarmEntityImport::collection()`. Because it is a normal property, the Edit form shows it, `FarmInfoModuleBuilder` includes it in the form's farm note, and the backfill can read it from Central.

### Sync fix (`syncLocalFarm()`)

Apply in **both** branches (adopt new, and the existing-farm `team_code` update at the end):

- If the team has `farm_name_property` set, take `team_code` from the entity's **`team_code` property**; if that is missing or blank, fall back to the label.
- If the team has no `farm_name_property`, keep today's behaviour exactly (team_code follows the label).

Gating on the team setting means no existing team changes behaviour until it opts in, and the existing "label edited directly in Central updates team_code" behaviour is preserved for them. For opted-in teams the `team_code` property is the source of truth, and label edits made directly in Central no longer flow back; that is the unavoidable cost of a composite label.

### Backfill command

`odk:backfill-farm-labels`, following the existing convention in `BackfillTemplateSettings` (dry run by default, `--apply` writes):

```
php artisan odk:backfill-farm-labels {--team=* } {--name-property=} {--apply}
```

- Teams: `--team` ids, default all teams that have an ODK project and a local farm dataset.
- `--name-property` must be a sanitized variable name that exists in the team's local `Dataset` variables (validated; otherwise the team is reported and skipped). If given it is saved to `teams.farm_name_property`, and in dry-run only reported. Without it the team's existing setting is used; a team with neither is skipped.
- Reads Central's live feed (`getOdkDatasetEntitiesFeed()`), **not** `refreshFromCentral()`, so the backfill never runs the sync code. Entities are matched to local farms by `odk_uuid`.
- For each entity with a non-blank value in the name property: code = `team_code` property, else the local `FarmEntity.team_code`; compute `buildFarmLabel()`; skip if it equals the current label. Entities with no name value keep their label.
- Update uses `updateOdkEntity()` with `data = [nameProperty => itsCurrentValue]`, a no-op merge. This avoids sending an empty `data`, which PHP serialises as `[]` rather than `{}`, and avoids the package change a label-only PATCH would need. `baseVersion` comes from the feed entity's version (`__system.version`); **verify on staging** that the OData feed includes it, otherwise fetch it per entity with `getOdkEntity()`.
- Per-entity failures (e.g. 409 version conflict because someone edited meanwhile) are reported and the run continues; re-running is safe because matching labels are skipped. Saves the new `odk_version` locally.
- Dry run prints a table of `team | farm code | current label | new label`, then totals: `N updated, N unchanged, N skipped (no name), N failed`.
- Soft-deleted entities are not in the feed and are left alone.

## Implementation steps

1. **Migration** (host app): nullable `teams.farm_name_property`.
2. **Service** (`OdkFarmEntityService`)
   - Add `buildFarmLabel()`.
   - `bulkCreateFarms(Team $team, Collection $rows, ?string $sourceName = null, ?string $nameColumn = null)`: rows carry an optional `name`; label from `buildFarmLabel()` when `$nameColumn` is set. The name value is also put in the row's identifiers under the `$nameColumn` key. After `reconcileProperties()`, save `$propertyMap[$nameColumn]` to `teams.farm_name_property`.
   - `createFarm()` / `updateFarm()`: after building `$data`, read the name as `$data[$team->farm_name_property] ?? null` (for `updateFarm()` after the "clear removed properties" loop, so a cleared name falls back to the code), and pass `buildFarmLabel()` to `createOdkEntity()` / `updateOdkEntity()`.
   - `syncLocalFarm()`: per the sync fix above, both branches.
3. **Import**
   - `FarmEntityImport::collection()`: resolve optional `farm_name_column` through `$headers` like the farm code column; add `name` to each prepared row and put the column in the identifiers; pass the header as `$nameColumn` to `bulkCreateFarms()`. The whole `$data` array already flows through `QueueFarmEntityImport` and `ImportFarmsAction`, so no plumbing is needed for the new key.
   - Both import forms get an optional `Select::make('farm_name_column')` after `farm_code_column`, and the identifier/property lists disable the chosen name column (same `disableOptionWhen` pattern as the farm code):
     - `app/Filament/App/Clusters/LocationLevels/Resources/FarmEntityResource/Pages/ImportLocationsAndFarmEntities.php`
     - `app/Filament/Tables/Actions/ImportFarmsAction.php`
   - Helper text: explains the label format, that leaving it empty keeps the farm code as the label, that the name is treated as an identifier, and that farms already imported keep their label until the backfill is run.
4. **Backfill command** as specified above.
5. **Docs:** a change log in `docs/change-logs` when done.

## Tests (Pest)

- `buildFarmLabel()`: name present, null, blank, whitespace/newlines, name containing `/`.
- Bulk import with a name column → entities pushed with `ID: x / Name: y`, name stored tagged `identifier`, `teams.farm_name_property` holds the sanitized name (including a header literally called `name`). Without a name column → label is the code and the setting is untouched. Empty name cell → that row falls back to the code.
- Existing `Http::fake` bulk-create order test still passes (rows are still sent in reverse).
- `createFarm()` / `updateFarm()` with the setting: label is composite; editing or clearing the name changes the label; with no setting: label is the code.
- `syncLocalFarm()` with the setting: an existing farm whose label is `ID: 154 / Name: ...` and `team_code` property `154` keeps `team_code` `154` (the regression this plan exists to prevent); adopting such an entity creates `team_code` `154`; with the property missing it falls back to the label. Without the setting, the existing `OdkFarmEntityRefreshTest` cases are unchanged (feeds there carry only a label).
- Backfill: dry run writes nothing; `--apply` updates only mismatched labels and leaves entities with no name alone; a second run is a no-op; `--name-property` not in the dataset is rejected; a failing PATCH is reported and the run continues.

## Risks and notes

- **Forms matching on label:** checked, nothing does. The form's farm calculations look entities up by `name` (uuid) and read property columns (`instance('Farm_Summary')/root/item[name=${id}]/...`), so a label change does not break them. The new name identifier appears in the farm note automatically the next time the Farm Info module is rebuilt.
- **Re-importing the same file does not relabel existing farms:** `bulkCreateFarms()` skips rows whose `team_code` already exists. Relabelling existing farms is the backfill's job.
- **Mobile cache:** ODK Collect devices pick up new labels only after their next entity list sync.
- **Chunked imports:** `FarmEntityImport` runs per 1000-row chunk, so saving `farm_name_property` happens once per chunk. It is an idempotent write of the same value.
- **Name column is optional on purpose:** teams without farmer names are unaffected, and so are existing teams until they opt in.
- **Submodule:** none of the steps touch `packages/filament-odk-link` (the backfill reuses the existing `updateOdkEntity()`), so no submodule commit or pointer bump is needed.

## Out of scope

- Per-team configurable label templates.
- A name selector on the manual create/edit farm forms (they follow the team's stored setting).
- Using a name column from the location (not farm) import.

## Verification notes (2026-11-02)

Changes made to the first draft after checking it against the code:

| # | First draft | Correction |
|---|---|---|
| 1 | Sync fix covered only the "adopt new entity" branch | The existing-farm branch (`syncLocalFarm()` last block) also copies label to `team_code` on every refresh; it would have corrupted every farm's `team_code`. Both branches fixed, gated on the team setting. |
| 2 | Backfill flag `--dry-run` | Repo convention (`odk:backfill-template-settings`) is dry run by default with `--apply`. |
| 3 | Name stored as a plain property | Stored as `identifier`, matching the form's data-protection wording. |
| 4 | Setting stored as "the sanitized name" without saying how to get it | Must come from `reconcileProperties()`'s map; a header called `name` is renamed because it is banned in ODK. |
| 5 | Rejected storing on Dataset because "it is global" | Wrong: the dataset is already per team. Decision unchanged, rationale corrected. |
| 6 | Backfill would "send the entity's existing data unchanged" | Feed values can be null and an empty `data` serialises as `[]`; sending only the name property's own value is a valid no-op merge and needs no package change. |
| 7 | Risk "check forms for label matching" left open | Checked: nothing matches on label (see Risks). |
