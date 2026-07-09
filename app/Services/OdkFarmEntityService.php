<?php

namespace App\Services;

use App\Models\SampleFrame\FarmEntity;
use App\Models\Team;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\DatasetVariable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkDataset;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

/**
 * Orchestrates farms as ODK Central Entities. Deliberately app-specific (not part of the
 * filament-odk-link package yet) - see docs/plans/odk-entities-farm-crud.md for why. The
 * dataset/entity API calls this delegates to on OdkLinkService are generic and
 * dataset-agnostic, and are the part expected to move into the package later.
 *
 * A farm's actual identifiers/properties are never persisted locally - no local mirror of
 * per-record values exists (see docs/plans/farm-entities-simplify-and-gps-sync.md). Every
 * read goes live to Central (getEntityData() for one farm, refreshFromCentral() for a
 * team's whole list). `Dataset`/`DatasetVariable` are schema-level bookkeeping only (which
 * properties exist, their identifier/property type tag) and do stay local - only per-record
 * `Entity`/`EntityValue` writes were removed.
 *
 * Two separate names are in play here, deliberately kept distinct so this feature's local
 * bookkeeping never collides with the app's existing "Farms" Dataset (id 3, seeded for
 * unrelated entities-upload work):
 * - LOCAL_DATASET_NAME: this feature's own local Dataset row (schema bookkeeping only).
 * - the ODK Central entity list name: hardcoded via resolveEntityListName() - see that
 *   method's own doc comment.
 */
class OdkFarmEntityService
{
    public const LOCAL_DATASET_NAME = 'farm_entities';

    // GPS is synced to Central as fixed properties (like team_code) rather than stored
    // locally. Detected by property NAME (not the DatasetVariable.description tag used for
    // the identifier/property split) - matches how team_code is already detected, and
    // avoids trusting a tag that a pre-existing DatasetVariable might carry from before it
    // was ever reconciled as GPS (reconcileProperties() reuses an existing name match
    // without correcting its tag). See docs/plans/farm-entities-simplify-and-gps-sync.md.
    public const GPS_FIELDS = ['latitude', 'longitude', 'altitude', 'accuracy'];

    // Top-level fields ODK Central's OData entity feed returns alongside the dataset's
    // actual data properties - `label` in particular is not `__`-prefixed, so it isn't
    // caught by the generic system-field filter below and must be excluded explicitly.
    protected const RESERVED_ODATA_KEYS = ['label', 'geometry'];

    public function __construct(protected OdkLinkService $odkLinkService) {}

    /**
     * This feature's own Dataset definition, shared across all teams (owner_id null) -
     * each team's actual Central entity list is tracked separately via OdkDataset.
     */
    public function ensureDataset(): Dataset
    {
        return Dataset::firstOrCreate(
            ['name' => self::LOCAL_DATASET_NAME, 'owner_id' => null],
            ['label' => 'team_code'],
        );
    }

    /**
     * The ODK Central entity list name, hardcoded - every team's active Xlsform template
     * checked so far uses "Farm_Summary" for its `entities` sheet. Previously resolved
     * per-team via Xlsform -> XlsformTemplate -> TemplateEntityList.list_name; see git
     * history if that ever needs restoring (e.g. a future template uses a different name).
     */
    public function resolveEntityListName(Team $team): ?string
    {
        return 'Farm_Summary';
    }

    public function ensureOdkDataset(Team $team, Dataset $dataset, string $entityListName): OdkDataset
    {
        $odkDataset = $this->findOdkDataset($team, $dataset, $entityListName);

        if ($odkDataset) {
            return $odkDataset;
        }

        $odkProject = $team->odkProject()->first();

        if ($odkProject === null) {
            throw new \RuntimeException("Team {$team->id} has no ODK Central project yet - cannot provision a farms entity list.");
        }

        $this->odkLinkService->createOdkDataset($odkProject, $entityListName);

        return OdkDataset::create([
            'dataset_id' => $dataset->id,
            'odk_project_id' => $odkProject->id,
            'owner_id' => $team->id,
            'name' => $entityListName,
        ]);
    }

    /**
     * Finds the local OdkDataset bookkeeping row for a team, self-healing it from Central
     * if the entity list already exists there but was never recorded locally - e.g. it was
     * created by an XLSForm's `entities` sheet (a registration form) rather than through
     * this app's Create page. Returns null only if the entity list genuinely doesn't
     * exist on Central yet for this team.
     */
    protected function findOdkDataset(Team $team, Dataset $dataset, string $entityListName): ?OdkDataset
    {
        $odkDataset = OdkDataset::where('dataset_id', $dataset->id)
            ->where('owner_id', $team->id)
            ->where('name', $entityListName)
            ->first();

        if ($odkDataset) {
            return $odkDataset;
        }

        $odkProject = $team->odkProject()->first();

        if ($odkProject === null) {
            return null;
        }

        try {
            $this->odkLinkService->getOdkDataset($odkProject, $entityListName);
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                return null;
            }

            throw $exception;
        }

        return OdkDataset::create([
            'dataset_id' => $dataset->id,
            'odk_project_id' => $odkProject->id,
            'owner_id' => $team->id,
            'name' => $entityListName,
        ]);
    }

    /**
     * Registers a property name discovered from Central as a local DatasetVariable if it
     * isn't already known - needed because entity_values.dataset_variable_name has a real
     * FK to dataset_variables.name, and entities created outside this app (e.g. by a
     * registration form's `entities` sheet) can carry properties we've never seen.
     */
    protected function ensurePropertyRegistered(Dataset $dataset, string $name): void
    {
        DatasetVariable::firstOrCreate(
            ['dataset_id' => $dataset->id, 'name' => $name],
            ['label' => $name, 'type' => 'string', 'description' => 'property'],
        );
    }

    /**
     * Ensures every key in $keyTypes exists as a DatasetVariable on the dataset and as a
     * property on the team's Central entity list, auto-creating any that are new. Returns
     * a map of raw key (e.g. a team's free-form identifier label) => the sanitized name
     * actually used as the ODK Central property/DatasetVariable name.
     *
     * $keyTypes is [rawKey => 'identifier'|'property'] - the type is stashed in the
     * DatasetVariable's `description` column (otherwise unused here) purely so the
     * identifiers/properties split can be reconstructed when editing later, since ODK
     * Central itself only stores flat property data with no such distinction.
     *
     * @param  array<string, string>  $keyTypes
     * @return array<string, string>
     */
    public function reconcileProperties(Team $team, Dataset $dataset, string $entityListName, array $keyTypes): array
    {
        $existing = $dataset->variables()->pluck('name', 'label')->all();
        $map = [];

        foreach ($keyTypes as $key => $type) {
            if (isset($existing[$key])) {
                $map[$key] = $existing[$key];

                continue;
            }

            $name = $this->uniquePropertyName($dataset, $key);

            DatasetVariable::create([
                'dataset_id' => $dataset->id,
                'name' => $name,
                'label' => $key,
                'type' => 'string',
                'description' => $type,
            ]);

            $this->odkLinkService->addOdkDatasetProperty($team->odkProject, $entityListName, $name);

            $existing[$key] = $name;
            $map[$key] = $name;
        }

        return $map;
    }

    /**
     * Property names that ODK Central reserves and will reject on an Entity list.
     * `name` and `label` are built-in Entity fields; `__` is a system prefix.
     */
    protected const BANNED_PROPERTY_NAMES = ['name', 'label', '__'];

    protected function uniquePropertyName(Dataset $dataset, string $key): string
    {
        $base = Str::of($key)->trim()->snake()->replaceMatches('/[^a-z0-9_]/', '')->value();
        $base = $base !== '' ? $base : 'property';

        // ODK Central rejects a fixed set of reserved property names. When the derived
        // name collides with one, append the snake-cased dataset name to disambiguate.
        if (in_array($base, self::BANNED_PROPERTY_NAMES, true)) {
            $datasetName = Str::of($dataset->name)->snake()->replaceMatches('/[^a-z0-9_]/', '')->value();
            $base = $datasetName !== '' ? "{$base}_{$datasetName}" : "{$base}_property";
        }

        $name = $base;
        $suffix = 1;

        while ($dataset->variables()->where('name', $name)->exists()) {
            $suffix++;
            $name = "{$base}_{$suffix}";
        }

        return $name;
    }

    /**
     * Creates a farm both on ODK Central and locally (structural row only - identifiers/
     * properties are never persisted locally, only read live via getEntityData()).
     *
     * @param  array<string, string>  $identifiers
     * @param  array<string, string>  $properties
     */
    public function createFarm(
        Team $team,
        int $locationId,
        string $teamCode,
        array $identifiers = [],
        array $properties = [],
        ?float $latitude = null,
        ?float $longitude = null,
        ?int $altitude = null,
        ?float $accuracy = null,
    ): FarmEntity {
        $entityListName = $this->resolveEntityListName($team);

        if ($entityListName === null) {
            throw new \RuntimeException("Team {$team->id} has no active Xlsform with an entities sheet - cannot determine which ODK Central entity list to write farms to.");
        }

        $dataset = $this->ensureDataset();
        $this->ensureOdkDataset($team, $dataset, $entityListName);

        $gpsData = array_filter([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'altitude' => $altitude,
            'accuracy' => $accuracy,
        ], fn ($value) => $value !== null);

        $rawData = [...$identifiers, ...$properties, ...$gpsData, 'team_code' => $teamCode];
        $keyTypes = [
            ...array_fill_keys(array_keys($identifiers), 'identifier'),
            ...array_fill_keys(array_keys($properties), 'property'),
            // GPS is detected by name elsewhere (see GPS_FIELDS doc comment), not by this
            // tag, so a plain 'property' tag is fine here.
            ...array_fill_keys(array_keys($gpsData), 'property'),
            'team_code' => 'property',
        ];
        $propertyMap = $this->reconcileProperties($team, $dataset, $entityListName, $keyTypes);

        $data = [];
        foreach ($rawData as $key => $value) {
            $data[$propertyMap[$key]] = (string) $value;
        }

        return DB::transaction(function () use ($team, $locationId, $teamCode, $entityListName, $data) {

            $farmEntity = FarmEntity::create([
                'owner_id' => $team->id,
                'location_id' => $locationId,
                'team_code' => $teamCode,
            ]);

            // NOTE: verify against the real ODK Central response during testing - the
            // exact shape of `currentVersion` has not been exercised against a live server yet.
            $odkEntity = $this->odkLinkService->createOdkEntity(
                $team->odkProject,
                $entityListName,
                $teamCode,
                $data,
            );

            $farmEntity->update([
                'odk_uuid' => $odkEntity['uuid'],
                'odk_version' => $odkEntity['currentVersion']['version'] ?? 1,
            ]);

            return $farmEntity;
        });
    }

    /**
     * Bulk-creates many farms in a single Central API call - used by the Excel import
     * flow instead of calling createFarm() per row, which would mean one Central round
     * trip per row on top of the reconciliation calls. Rows whose team_code already
     * exists for the team are skipped (mirrors the FarmImport dedup rule); a
     * team_code repeated within $rows itself is also deduped, keeping the first occurrence.
     *
     * @param  Collection<int, array{locationId: int, teamCode: string, identifiers: array<string, string>, properties: array<string, string>}>  $rows
     * @return Collection<int, FarmEntity>
     */
    public function bulkCreateFarms(Team $team, Collection $rows, ?string $sourceName = null): Collection
    {
        $entityListName = $this->resolveEntityListName($team);

        if ($entityListName === null) {
            throw new \RuntimeException("Team {$team->id} has no active Xlsform with an entities sheet - cannot import farms.");
        }

        $dataset = $this->ensureDataset();
        $this->ensureOdkDataset($team, $dataset, $entityListName);

        $existingCodes = FarmEntity::where('owner_id', $team->id)->pluck('team_code')->all();

        $newRows = $rows
            ->reject(fn ($row) => in_array($row['teamCode'], $existingCodes, true))
            ->unique('teamCode')
            ->values();

        if ($newRows->isEmpty()) {
            return collect();
        }

        // Reconcile every identifier/property key used across the whole batch up front,
        // rather than once per row.
        $keyTypes = ['team_code' => 'property'];

        foreach ($newRows as $row) {
            $keyTypes = [
                ...$keyTypes,
                ...array_fill_keys(array_keys($row['identifiers']), 'identifier'),
                ...array_fill_keys(array_keys($row['properties']), 'property'),
            ];
        }

        $propertyMap = $this->reconcileProperties($team, $dataset, $entityListName, $keyTypes);

        return DB::transaction(function () use ($team, $entityListName, $newRows, $propertyMap, $sourceName) {

            $prepared = $newRows->map(function ($row) use ($team, $propertyMap) {
                $rawData = [...$row['identifiers'], ...$row['properties'], 'team_code' => $row['teamCode']];

                $data = [];
                foreach ($rawData as $key => $value) {
                    $data[$propertyMap[$key]] = (string) $value;
                }

                $farmEntity = FarmEntity::create([
                    'owner_id' => $team->id,
                    'location_id' => $row['locationId'],
                    'team_code' => $row['teamCode'],
                    // Assigned client-side (rather than left to Central to generate) so
                    // the bulk-create response doesn't need to be matched back to rows.
                    'odk_uuid' => (string) Str::uuid(),
                ]);

                return ['farmEntity' => $farmEntity, 'uuid' => $farmEntity->odk_uuid, 'label' => $row['teamCode'], 'data' => $data];
            });

            $this->odkLinkService->bulkCreateOdkEntities(
                $team->odkProject,
                $entityListName,
                $prepared->map(fn ($p) => ['uuid' => $p['uuid'], 'label' => $p['label'], 'data' => $p['data']])->values()->all(),
                $sourceName,
            );

            return $prepared->pluck('farmEntity');
        });
    }

    /**
     * Splits a farm's current identifiers/properties/GPS back out for editing. Fetches the
     * entity's current data live from Central (no local mirror of values exists), then
     * looks each property name up against local DatasetVariable rows (schema metadata,
     * not per-record data) for its label and its 'identifier'/'property'/GPS-field type
     * tag - see reconcileProperties(). A name with no known DatasetVariable (e.g.
     * discovered from an entity created outside this app, not yet seen by
     * reconcileProperties/ensurePropertyRegistered) defaults to 'property'. Excludes
     * team_code, which has its own dedicated form field.
     *
     * @return array{identifiers: array<string, string>, properties: array<string, string>, latitude: ?string, longitude: ?string, altitude: ?string, accuracy: ?string}
     */
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

            if (in_array($name, self::GPS_FIELDS, true)) {
                $gps[$name] = $value;

                continue;
            }

            $label = $labelByName[$name] ?? $name;

            if (($typeByName[$name] ?? 'property') === 'identifier') {
                $identifiers[$label] = $value;
            } else {
                $properties[$label] = $value;
            }
        }

        return ['identifiers' => $identifiers, 'properties' => $properties, ...$gps];
    }

    /**
     * Updates a farm both on ODK Central (optimistic concurrency via odk_version as
     * baseVersion) and locally. Any identifier/property key no longer present is removed;
     * new ones are reconciled the same way createFarm() reconciles them.
     *
     * @param  array<string, string>  $identifiers
     * @param  array<string, string>  $properties
     */
    public function updateFarm(
        FarmEntity $farmEntity,
        int $locationId,
        string $teamCode,
        array $identifiers = [],
        array $properties = [],
        ?float $latitude = null,
        ?float $longitude = null,
        ?int $altitude = null,
        ?float $accuracy = null,
    ): FarmEntity {
        if ($farmEntity->odk_uuid === null) {
            throw new \RuntimeException("Farm {$farmEntity->id} has no linked ODK Central entity to update.");
        }

        $team = $farmEntity->owner;
        $entityListName = $this->resolveEntityListName($team);

        if ($entityListName === null) {
            throw new \RuntimeException("Team {$team->id} has no active Xlsform with an entities sheet - cannot determine which ODK Central entity list to update.");
        }

        $dataset = $this->ensureDataset();
        $this->ensureOdkDataset($team, $dataset, $entityListName);

        // No local mirror of values exists - fetch the entity's current data live to know
        // what it previously had, so anything no longer submitted can be explicitly cleared
        // below (Central's update merges, it doesn't replace - see the comment further down).
        $currentData = $this->odkLinkService->getOdkEntity($team->odkProject, $entityListName, $farmEntity->odk_uuid)['currentVersion']['data'] ?? [];
        $previouslySetNames = array_keys($currentData);

        $gpsData = array_filter([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'altitude' => $altitude,
            'accuracy' => $accuracy,
        ], fn ($value) => $value !== null);

        $rawData = [...$identifiers, ...$properties, ...$gpsData, 'team_code' => $teamCode];
        $keyTypes = [
            ...array_fill_keys(array_keys($identifiers), 'identifier'),
            ...array_fill_keys(array_keys($properties), 'property'),
            // GPS is detected by name elsewhere (see GPS_FIELDS doc comment), not by this
            // tag, so a plain 'property' tag is fine here.
            ...array_fill_keys(array_keys($gpsData), 'property'),
            'team_code' => 'property',
        ];
        $propertyMap = $this->reconcileProperties($team, $dataset, $entityListName, $keyTypes);

        $data = [];
        foreach ($rawData as $key => $value) {
            $data[$propertyMap[$key]] = (string) $value;
        }

        // ODK Central's entity update merges the given `data` with the entity's existing
        // data - omitting a property leaves its old value in place rather than removing
        // it. Anything this entity previously had a value for, but that's no longer
        // submitted, must be explicitly set to "" to actually clear it there.
        foreach ($previouslySetNames as $name) {
            if (! array_key_exists($name, $data)) {
                $data[$name] = '';
            }
        }

        return DB::transaction(function () use ($farmEntity, $team, $locationId, $teamCode, $entityListName, $data) {

            // NOTE: verify against a live server - baseVersion defaults to 1 if we never
            // recorded one (e.g. a farm adopted from an entity created outside this app).
            $odkEntity = $this->odkLinkService->updateOdkEntity(
                $team->odkProject,
                $entityListName,
                $farmEntity->odk_uuid,
                $teamCode,
                $data,
                $farmEntity->odk_version ?? 1,
            );

            $farmEntity->update([
                'location_id' => $locationId,
                'team_code' => $teamCode,
                'odk_version' => $odkEntity['currentVersion']['version'] ?? ($farmEntity->odk_version ?? 1) + 1,
            ]);

            return $farmEntity->fresh();
        });
    }

    /**
     * Soft-deletes a farm's entity on ODK Central, then soft-deletes the local FarmEntity
     * row (not hard-deleted - keeps it around for any future FK references, e.g. from
     * FarmSurveyData once this is wired up at cutover).
     */
    public function deleteFarm(FarmEntity $farmEntity): void
    {
        if ($farmEntity->odk_uuid === null) {
            throw new \RuntimeException("Farm {$farmEntity->id} has no linked ODK Central entity to delete.");
        }

        $team = $farmEntity->owner;
        $entityListName = $this->resolveEntityListName($team);

        if ($entityListName === null) {
            throw new \RuntimeException("Team {$team->id} has no active Xlsform with an entities sheet - cannot determine which ODK Central entity list to delete from.");
        }

        $this->odkLinkService->deleteOdkEntity($team->odkProject, $entityListName, $farmEntity->odk_uuid);

        $farmEntity->delete();
    }

    /**
     * Restores a soft-deleted farm's entity on ODK Central, then restores the local
     * FarmEntity row.
     */
    public function restoreFarm(FarmEntity $farmEntity): void
    {
        if ($farmEntity->odk_uuid === null) {
            throw new \RuntimeException("Farm {$farmEntity->id} has no linked ODK Central entity to restore.");
        }

        $team = $farmEntity->owner;
        $entityListName = $this->resolveEntityListName($team);

        if ($entityListName === null) {
            throw new \RuntimeException("Team {$team->id} has no active Xlsform with an entities sheet - cannot determine which ODK Central entity list to restore from.");
        }

        $this->odkLinkService->restoreOdkEntity($team->odkProject, $entityListName, $farmEntity->odk_uuid);

        $farmEntity->restore();
    }

    /**
     * Syncs structural FarmEntity rows for every farm belonging to $team from Central's
     * live OData feed (creating/restoring rows for farms that exist on Central but not
     * locally yet, e.g. created directly by a registration form's `entities` sheet -
     * "adopted" with location left unset since we have no way to infer it), and returns
     * the fetched feed as a live data map for the caller to use directly - nothing about
     * a farm's actual property values is persisted locally.
     *
     * NOTE: the `__id` key for an entity's uuid in the OData response is based on ODK's
     * documented convention, not yet confirmed against a live response - verify during testing.
     *
     * @return array<string, array{label: ?string, data: array<string, string>}>
     */
    public function refreshFromCentral(Team $team): array
    {
        $entityListName = $this->resolveEntityListName($team);

        if ($entityListName === null) {
            // No active form with an entities sheet for this team yet - nothing to read.
            return [];
        }

        $dataset = $this->ensureDataset();
        $odkDataset = $this->findOdkDataset($team, $dataset, $entityListName);

        if ($odkDataset === null) {
            // Nothing has ever been created for this team, either through this app or
            // directly on Central - there is genuinely nothing to fetch yet.
            return [];
        }

        $odkProject = $team->odkProject()->first();

        if ($odkProject === null) {
            return [];
        }

        $feed = $this->odkLinkService->getOdkDatasetEntitiesFeed($odkProject, $entityListName);

        // withTrashed(): the feed only returns Central's currently-active entities, but a
        // uuid in it may belong to a farm we soft-deleted locally that was since restored
        // on Central - that needs restoring, not re-inserting (which would collide on the
        // unique odk_uuid constraint).
        $farmsByUuid = FarmEntity::withTrashed()
            ->where('owner_id', $team->id)
            ->whereNotNull('odk_uuid')
            ->get()
            ->keyBy('odk_uuid');

        $liveData = [];

        foreach ($feed as $row) {
            $uuid = $row['__id'] ?? null;

            if (! $uuid) {
                continue;
            }

            $values = collect($row)->filter(fn ($value, $key) => ! Str::startsWith($key, '__') && ! in_array($key, self::RESERVED_ODATA_KEYS, true));

            $liveData[$uuid] = ['label' => $row['label'] ?? null, 'data' => $values->all()];

            $farmEntity = $farmsByUuid->get($uuid);

            if ($farmEntity?->trashed()) {
                $farmEntity->restore();
            }

            if (! $farmEntity) {
                FarmEntity::create([
                    'owner_id' => $team->id,
                    'location_id' => null,
                    'team_code' => (string) ($values->get('team_code') ?? $row['label'] ?? $uuid),
                    'odk_uuid' => $uuid,
                ]);
            }

            foreach ($values as $name => $value) {
                $this->ensurePropertyRegistered($dataset, $name);
            }
        }

        return $liveData;
    }
}
