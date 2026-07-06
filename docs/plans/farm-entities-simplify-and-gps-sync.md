# Plan: Simplify entity-list resolution, drop entity/entity_values persistence, close the GPS gap

**Status: In Progress** — Parts A and B confirmed working end-to-end. Part C implemented as planned, passing `phpstan`/`pint`/the full test suite, awaiting Dan's manual test.

## Part C implementation notes

Implemented as planned: `OdkFarmEntityService::GPS_FIELDS` constant added; `createFarm()`/`updateFarm()` fold any provided GPS values into `$rawData`/`$keyTypes` as fixed optional properties (each tagged with its own name, reusing the `reconcileProperties()`/`DatasetVariable.description` mechanism); `getEntityData()` routes GPS-tagged properties to dedicated `latitude`/`longitude`/`altitude`/`accuracy` return keys instead of the identifiers/properties KeyValue split. `FarmEntity::$casts` and the GPS columns themselves are removed (migration, confirmed no existing non-null GPS data first). `FarmEntityResource::table()` excludes GPS-tagged variables from the dynamic property columns, matching the old `FarmResource` (which never showed GPS as list columns either).

## Part B implementation notes

Implemented as planned: added `OdkDatasetService::getOdkEntity()` (package), removed all `Entity`/`EntityValue` reads/writes from `createFarm()`, `bulkCreateFarms()`, `updateFarm()`, `getEntityData()`, `refreshFromCentral()` (now returns `array` instead of `void` - the live feed, for callers to use directly). Removed `FarmEntity::entity()` (unused now). `ListFarmEntities` holds the fetched feed in a public `$liveFarmData` property; `FarmEntityResource::table()`'s dynamic columns read from it via Filament's `$livewire` closure injection instead of `$record->entity->values`. `EditFarmEntity::mutateFormDataBeforeFill()` no longer calls the whole-team refresh, since `getEntityData()` does its own single-entity live fetch now.

Cleaned up leftover test data from before this change: 23 `Entity` rows (798 `EntityValue` rows) that the old code had created for farms, deleted via tinker - the tables themselves aren't touched (shared with unrelated package functionality), just no longer written to by this feature.

Follow-up to [odk-entities-farm-crud.md](odk-entities-farm-crud.md) (the main Farm-on-ODK-Central-Entities feature, Phases 0–5 complete and confirmed working). This plan covers three further simplifications identified during an architecture-reconsideration discussion after that feature was built, agreed to be reviewed together since they all touch the same file (`app/Services/OdkFarmEntityService.php`) and are easiest to reason about as one coherent end-state.

## Context

1. **`resolveEntityListName()` can be hardcoded.** It currently does a per-team lookup (`Xlsform` → `XlsformTemplate` → `TemplateEntityList.list_name`) because a team's active form's `entities` sheet determines the real Central list name. In practice every template checked so far uses `"Farm_Summary"`. Hardcoding removes a chain of joins for zero observed variation, at the cost of needing to revisit this if a future template ever uses a different name.
2. **Farm content shouldn't be persisted into `entities`/`entity_values`.** These generic package tables were used as a "read-through cache" (refreshed from Central on every view, never trusted as stale) — but per the investigation in `docs/prompts/why-local-table-for-externally-stored-data.md`, persisting content locally at all isn't necessary; it can be fetched live at the point of use instead. `Dataset`/`DatasetVariable` (schema-level: which properties exist, their identifier/property/gps type tag) are unaffected — this is only about removing writes of per-record *values*.
3. **GPS fields should become Central-synced properties, not local-only columns.** `farm_entities.latitude/longitude/altitude/accuracy` are the one place local storage still holds real farm content, contradicting the "structural only" design of everything else in that table.

Changes 2 and 3 are handled together below since GPS's read/write path is naturally described as part of the same `createFarm()`/`updateFarm()`/`getEntityData()` rewrite that change 2 requires anyway — describing them as two separate passes over the same methods would just mean rewriting the same code twice.

## Part A — hardcode `resolveEntityListName()`

```php
public function resolveEntityListName(Team $team): ?string
{
    return 'Farm_Summary';
}
```

Keep the `Team $team` parameter (unused for now) so every call site is unaffected — this is a one-line body swap, not a signature change. Leave a comment noting the removed per-team lookup and pointing at git history if it needs restoring.

## Part B + C — stop persisting content; GPS becomes a synced property

### New package method

**`packages/filament-odk-link/.../OdkDatasetService.php`** — add `getOdkEntity(OdkProject $odkProject, string $datasetName, string $uuid): array`, a `GET .../datasets/{name}/entities/{uuid}` wrapper (mirrors the existing `createOdkEntity`/`updateOdkEntity` methods in the same file). Needed because without a local content mirror, both editing a farm and diffing "what changed" require fetching one farm's current data directly, rather than pulling the whole team's feed to find one row.

### `app/Services/OdkFarmEntityService.php` — end state per method

Add `public const GPS_FIELDS = ['latitude', 'longitude', 'altitude', 'accuracy'];` near `LOCAL_DATASET_NAME`.

**`createFarm()` / `bulkCreateFarms()`** — build `$rawData`/`$keyTypes` to include GPS as optional fixed properties (only send what's provided, each tagged with its own name as its type — reuses the existing `reconcileProperties()`/`DatasetVariable.description` tagging mechanism, same as `team_code`):

```php
$gpsData = array_filter([
    'latitude' => $latitude, 'longitude' => $longitude,
    'altitude' => $altitude, 'accuracy' => $accuracy,
], fn ($v) => $v !== null);

$rawData = [...$identifiers, ...$properties, ...$gpsData, 'team_code' => $teamCode];
$keyTypes = [
    ...array_fill_keys(array_keys($identifiers), 'identifier'),
    ...array_fill_keys(array_keys($properties), 'property'),
    ...array_combine(array_keys($gpsData), array_keys($gpsData)),
    'team_code' => 'property',
];
```

Drop the `Entity::create([...])` + `$entity->addValues(...)` block entirely, and drop `latitude`/`longitude`/`altitude`/`accuracy` from the `FarmEntity::create([...])` call (Part C's schema change makes those columns gone anyway).

**`updateFarm()`** — same `$rawData`/`$keyTypes` construction as above. Replace the "what was previously set" lookup:

```php
// before: $entity = $farmEntity->entity()->firstOrFail(); $previouslySetNames = $entity->values()->pluck(...);
$currentData = $this->odkLinkService->getOdkEntity($team->odkProject, $entityListName, $farmEntity->odk_uuid)['currentVersion']['data'] ?? [];
$previouslySetNames = array_keys($currentData);
```

The existing "clear removed keys to `''`" diff logic is unchanged — it already generalizes to GPS with no further edits. Drop GPS from the `$farmEntity->update([...])` call, and drop the `EntityValue::updateOrCreate(...)` loop entirely (nothing left to persist locally).

**Trade-off to note for visibility, not to solve now:** this adds one extra API call per edit (fetch-then-patch instead of just patch).

**`getEntityData()`** — full live fetch, no local relation at all:

```php
public function getEntityData(FarmEntity $farmEntity): array
{
    $gps = array_fill_keys(self::GPS_FIELDS, null);

    if ($farmEntity->odk_uuid === null) {
        return ['identifiers' => [], 'properties' => [], ...$gps];
    }

    $team = $farmEntity->owner;
    $entityListName = $this->resolveEntityListName($team);
    $dataset = $this->ensureDataset();
    $data = $this->odkLinkService->getOdkEntity($team->odkProject, $entityListName, $farmEntity->odk_uuid)['currentVersion']['data'] ?? [];

    $typeByName = $dataset->variables()->pluck('description', 'name');
    $labelByName = $dataset->variables()->pluck('label', 'name');

    $identifiers = [];
    $properties = [];

    foreach ($data as $name => $value) {
        if ($name === 'team_code') {
            continue;
        }

        $type = $typeByName[$name] ?? 'property';

        if (in_array($type, self::GPS_FIELDS, true)) {
            $gps[$type] = $value;
            continue;
        }

        $label = $labelByName[$name] ?? $name;
        ($type === 'identifier' ? $identifiers : $properties)[$label] = $value;
    }

    return ['identifiers' => $identifiers, 'properties' => $properties, ...$gps];
}
```

**`refreshFromCentral()`** — keep the structural adopt/restore logic (creating/restoring `FarmEntity` rows for farms present on Central but not locally — unaffected by this plan, still needed for farms created outside this app). Drop the `Entity::create(...)` and `EntityValue::updateOrCreate(...)` calls; keep `ensurePropertyRegistered()` (schema-only). Change return type from `void` to `array`, returning the fetched feed as `[uuid => ['label' => ..., 'data' => [name => value, ...]]]` for the caller to use directly instead of querying a local mirror.

### Callers

**`app/Models/SampleFrame/FarmEntity.php`** — remove the `entity(): MorphOne` relation (no longer referenced anywhere) and its `Entity` import; remove `latitude`/`longitude`/`altitude`/`accuracy` from `$casts`; update the class doc comment.

**`FarmEntityResource.php` → `table()`** — dynamic property columns: exclude GPS-tagged variables (`->whereNotIn('description', OdkFarmEntityService::GPS_FIELDS)`, alongside the existing `team_code` name exclusion — matches the old `FarmResource`, which never showed GPS as list columns either). Read values via the live data map instead of `$record->entity?->values`:

```php
->getStateUsing(fn (FarmEntity $record, $livewire) => $livewire->liveFarmData[$record->odk_uuid]['data'][$variable->name] ?? null)
```

**`ListFarmEntities.php`** — add `public array $liveFarmData = [];`; in `mount()`, `$this->liveFarmData = app(OdkFarmEntityService::class)->refreshFromCentral(...)` (was previously called for its side effect only, ignoring the return value).

**`EditFarmEntity.php` → `mutateFormDataBeforeFill()`** — drop the whole-team `refreshFromCentral()` call; `getEntityData()` now does its own single-entity live fetch, so it's no longer needed here. Simplifies to just `[...$data, ...app(OdkFarmEntityService::class)->getEntityData($this->getFarmEntity())]`.

### Schema

New migration dropping `latitude`/`longitude`/`altitude`/`accuracy` from `farm_entities` (targeted column drop, not touching the original create-table migration — existing `team_code`/`odk_uuid`/`location_id` rows untouched). **Before running it:** check for existing non-null GPS values in the dev DB; if any exist, they're lost on drop unless manually pushed to Central first via the new code path (likely none — GPS wasn't confirmed tested during this feature's development).

**Cleanup (not a migration):** delete leftover `Entity`/`EntityValue` rows this feature already created (`Entity::where('model_type', FarmEntity::class)->get()->each->delete()` or similar, cascading to their `EntityValue` children) — these tables aren't dropped (shared with unrelated package functionality), just no longer written to by this feature going forward.

## Verification

1. `./vendor/bin/pint --dirty`, `./vendor/bin/phpstan analyse` on every touched file, `./vendor/bin/pest` (full suite) — same checks used throughout this feature.
2. Confirm `getOdkEntity()`'s actual response shape against a live server (the `currentVersion.data` path is inferred from the same entity-object shape already used elsewhere in this codebase, not yet confirmed against a real response for a `GET` on a single entity specifically).
3. Manually re-test, against the real ODK Central server, every operation already validated earlier in this feature's development (list, create, edit — including clearing a property, delete, both import flows) to confirm no regression from removing the local content mirror.
4. Specifically for GPS: create a farm with GPS filled in, confirm the properties appear on the Central entity; edit and clear one GPS field, confirm it's cleared to `""` on Central and the Edit form doesn't misfile it into the Properties KeyValue widget; confirm the List table does not show GPS as extra columns.
5. Specifically for the entity-list-name hardcode: confirm nothing currently relies on a team having an *inactive* or non-"Farm_Summary"-named entity list — if any test team's active form doesn't use that exact list name, farm creation for that team will now fail loudly (`RuntimeException` from `ensureOdkDataset`) rather than resolving correctly.
