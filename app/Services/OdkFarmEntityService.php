<?php

namespace App\Services;

use App\Models\SampleFrame\FarmEntity;
use App\Models\Team;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\DatasetVariable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkDataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

/**
 * Orchestrates farms as ODK Central Entities. Deliberately app-specific (not part of the
 * filament-odk-link package yet) - see docs/plans/odk-entities-farm-crud.md for why. The
 * dataset/entity API calls this delegates to on OdkLinkService are generic and
 * dataset-agnostic, and are the part expected to move into the package later.
 *
 * Two separate names are in play here, deliberately kept distinct so this feature's local
 * bookkeeping never collides with the app's existing "Farms" Dataset (id 3, seeded for
 * unrelated entities-upload work):
 * - LOCAL_DATASET_NAME: this feature's own local Dataset row (schema bookkeeping only).
 * - the ODK Central entity list name: whatever a team's active Xlsform's `entities` sheet
 *   actually calls it (e.g. "Farm_Summary") - resolved per-team via resolveEntityListName().
 */
class OdkFarmEntityService
{
    public const LOCAL_DATASET_NAME = 'farm_entities';

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
     * Resolves the actual ODK Central entity list name for a team, from whichever of its
     * currently-active Xlsforms has an `entities` sheet defined on its template. Returns
     * null if the team has no active form with an entity list yet.
     */
    public function resolveEntityListName(Team $team): ?string
    {
        $xlsform = Xlsform::where('owner_id', $team->id)
            ->where('is_active', true)
            ->whereHas('xlsformTemplate.templateEntityLists')
            ->with('xlsformTemplate.templateEntityLists')
            ->first();

        return $xlsform?->xlsformTemplate?->templateEntityLists->first()?->list_name;
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
            ['label' => $name, 'type' => 'string'],
        );
    }

    /**
     * Ensures every key in $keys exists as a DatasetVariable on the dataset and as a
     * property on the team's Central entity list, auto-creating any that are new.
     * Returns a map of raw key (e.g. a team's free-form identifier label) => the
     * sanitized name actually used as the ODK Central property/DatasetVariable name.
     *
     * @param  array<int, string>  $keys
     * @return array<string, string>
     */
    public function reconcileProperties(Team $team, Dataset $dataset, string $entityListName, array $keys): array
    {
        $existing = $dataset->variables()->pluck('name', 'label')->all();
        $map = [];

        foreach (array_unique($keys) as $key) {
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
            ]);

            $this->odkLinkService->addOdkDatasetProperty($team->odkProject, $entityListName, $name);

            $existing[$key] = $name;
            $map[$key] = $name;
        }

        return $map;
    }

    protected function uniquePropertyName(Dataset $dataset, string $key): string
    {
        $base = Str::of($key)->trim()->snake()->replaceMatches('/[^a-z0-9_]/', '')->value();
        $base = $base !== '' ? $base : 'property';

        $name = $base;
        $suffix = 1;

        while ($dataset->variables()->where('name', $name)->exists()) {
            $suffix++;
            $name = "{$base}_{$suffix}";
        }

        return $name;
    }

    /**
     * Creates a farm both on ODK Central and locally (structural row + generic Entity/
     * EntityValue rows, which act as the read-through cache refreshed by refreshFromCentral()).
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

        $rawData = [...$identifiers, ...$properties, 'team_code' => $teamCode];
        $propertyMap = $this->reconcileProperties($team, $dataset, $entityListName, array_keys($rawData));

        $data = [];
        foreach ($rawData as $key => $value) {
            $data[$propertyMap[$key]] = (string) $value;
        }

        return DB::transaction(function () use ($team, $locationId, $teamCode, $latitude, $longitude, $altitude, $accuracy, $dataset, $entityListName, $data) {

            $farmEntity = FarmEntity::create([
                'owner_id' => $team->id,
                'location_id' => $locationId,
                'team_code' => $teamCode,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'altitude' => $altitude,
                'accuracy' => $accuracy,
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

            $entity = Entity::create([
                'dataset_id' => $dataset->id,
                'owner_id' => $team->id,
                'model_type' => FarmEntity::class,
                'model_id' => $farmEntity->id,
            ]);

            $entity->addValues(
                collect($data)->map(fn ($value, $name) => new EntityValue([
                    'dataset_variable_name' => $name,
                    'value' => $value,
                ]))->values()
            );

            return $farmEntity;
        });
    }

    /**
     * Refreshes local EntityValue rows for every farm belonging to $team from Central's
     * live OData feed - the "read-through, no stale cache" read path. Called before
     * rendering the farm list/detail views.
     *
     * Entities that exist on Central but have no matching local FarmEntity (e.g. created
     * directly by a registration form's `entities` sheet, not through this app's Create
     * page) are "adopted": a FarmEntity/Entity row is created for them on the fly, with
     * location left unset since we have no way to infer it from an externally-created entity.
     *
     * NOTE: the `__id` key for an entity's uuid in the OData response is based on ODK's
     * documented convention, not yet confirmed against a live response - verify during testing.
     */
    public function refreshFromCentral(Team $team): void
    {
        $entityListName = $this->resolveEntityListName($team);

        if ($entityListName === null) {
            // No active form with an entities sheet for this team yet - nothing to read.
            return;
        }

        $dataset = $this->ensureDataset();
        $odkDataset = $this->findOdkDataset($team, $dataset, $entityListName);

        if ($odkDataset === null) {
            // Nothing has ever been created for this team, either through this app or
            // directly on Central - there is genuinely nothing to fetch yet.
            return;
        }

        $odkProject = $team->odkProject()->first();

        if ($odkProject === null) {
            return;
        }

        $feed = $this->odkLinkService->getOdkDatasetEntitiesFeed($odkProject, $entityListName);

        $farmsByUuid = FarmEntity::query()
            ->where('owner_id', $team->id)
            ->whereNotNull('odk_uuid')
            ->with('entity')
            ->get()
            ->keyBy('odk_uuid');

        foreach ($feed as $row) {
            $uuid = $row['__id'] ?? null;

            if (! $uuid) {
                continue;
            }

            $values = collect($row)->filter(fn ($value, $key) => ! Str::startsWith($key, '__'));

            $farmEntity = $farmsByUuid->get($uuid);
            $entity = $farmEntity?->entity;

            if (! $farmEntity) {
                $farmEntity = FarmEntity::create([
                    'owner_id' => $team->id,
                    'location_id' => null,
                    'team_code' => (string) ($values->get('team_code') ?? $row['label'] ?? $uuid),
                    'odk_uuid' => $uuid,
                ]);
            }

            if (! $entity) {
                $entity = Entity::create([
                    'dataset_id' => $dataset->id,
                    'owner_id' => $team->id,
                    'model_type' => FarmEntity::class,
                    'model_id' => $farmEntity->id,
                ]);
            }

            foreach ($values as $name => $value) {
                $this->ensurePropertyRegistered($dataset, $name);

                EntityValue::updateOrCreate(
                    ['entity_id' => $entity->id, 'dataset_variable_name' => $name],
                    ['value' => (string) $value],
                );
            }
        }
    }
}
