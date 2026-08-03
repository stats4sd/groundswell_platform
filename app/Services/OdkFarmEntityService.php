<?php

namespace App\Services;

use App\Models\SampleFrame\FarmEntity;
use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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

    // Legacy GPS property names - farms created by this app before the `geometry` format
    // (see GEOMETRY_FIELD below) stored GPS as four separate properties instead of one.
    // Detected by property NAME (not the DatasetVariable.description tag used for the
    // identifier/property split) - matches how team_code is already detected, and avoids
    // trusting a tag that a pre-existing DatasetVariable might carry from before it was
    // ever reconciled as GPS (reconcileProperties() reuses an existing name match without
    // correcting its tag). See docs/plans/farm-entities-simplify-and-gps-sync.md. Still
    // read (getEntityData()) for backward compatibility with farms already written this
    // way; no longer written (createFarm()/updateFarm() now write GEOMETRY_FIELD instead).
    public const GPS_FIELDS = ['latitude', 'longitude', 'altitude', 'accuracy'];

    // Farms registered directly in Enketo (the Farm Registration XLSForm) store GPS as a
    // single property in this space-separated "latitude longitude altitude accuracy"
    // format (ODK's own geopoint string representation, e.g. "45.4215 -75.6972 70.0 4.5"),
    // not as four separate properties. createFarm()/updateFarm() now write this same format
    // so both creation paths agree; getEntityData() reads it back into the same four GPS
    // form fields the app already has (see the GPS section on FarmEntityResource).
    public const GEOMETRY_FIELD = 'geometry';

    // Top-level fields ODK Central's OData entity feed returns alongside the dataset's
    // actual data properties - `label` in particular is not `__`-prefixed, so it isn't
    // caught by the generic system-field filter below and must be excluded explicitly.
    protected const RESERVED_ODATA_KEYS = ['label', 'geometry'];

    public function __construct(protected OdkLinkService $odkLinkService) {}

    /**
     * This feature's own Dataset definition, one per team (owner_id = $team->id). Teams'
     * Central entity lists are separate schemas, so the local DatasetVariable bookkeeping
     * that tracks "has this property already been pushed to Central" must be scoped the
     * same way - a shared Dataset across teams let one team's already-pushed property
     * silently skip the push for another team that happened to use the same raw key,
     * leaving that team's Central entity list missing the property (see
     * docs/change-logs/team-scoped-farm-entities-dataset.md). Each team's actual Central
     * entity list is tracked separately via OdkDataset.
     *
     * The name carries the team id suffix (not just `owner_id`) because this app's
     * `datasets` table enforces a single-column `unique(name)` - unlike the
     * filament-odk-link package's own migration, which defines a composite
     * `unique([name, owner_id])` (see
     * database/migrations/03_xlsform_management/2024_03_10_03_101232_1_create_datasets_table.php
     * vs. the package's `000_create_datasets_table.php`). Loosening that constraint would
     * affect every other (global, owner_id-null) Dataset in the app, so this stays scoped
     * to just this one feature instead.
     */
    public function ensureDataset(Team $team): Dataset
    {
        return Dataset::firstOrCreate(
            ['name' => self::LOCAL_DATASET_NAME.'_'.$team->id, 'owner_id' => $team->id],
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

    /**
     * Resolves the `location_id` for an entity adopted from Central, from its `loc{n}`
     * attributes (the Farm Registration XLSForm's `entities` sheet convention, one
     * `loc{n}`/`loc{n}_name`/`loc{n}_type` triplet per location level, `loc1` topmost).
     * `loc{n}` carries the authoritative `locations.id` - the entity list synced to
     * Central uses ids as choice values, and buildLocationAttributes() writes the same -
     * so the deepest `loc{n}` present is matched by primary key first. Entities written
     * before the id switch hold codes or placeholder values in `loc{n}`, so the deepest
     * `loc{n}_name` remains as a fallback, matched case-insensitively since ODK data
     * entry and the app's own Location names may differ only in case. Both paths match
     * only against Locations the team already has, at the level the position implies -
     * never creating one - and return null when nothing matches.
     *
     * @param  array<string, mixed>  $data
     */
    public function resolveLocationFromAttributes(Team $team, array $data): ?int
    {
        $chain = LocationLevel::farmLevelChain($team);

        if ($chain->isEmpty()) {
            return null;
        }

        $idsByPos = [];
        $namesByPos = [];

        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (preg_match('/^loc(\d+)$/', $key, $matches)) {
                // Digits-only guard: legacy code values like "1V" must not reach the id
                // query - MySQL's string-to-int cast would silently match them to id 1.
                if (ctype_digit((string) $value)) {
                    $idsByPos[(int) $matches[1]] = (int) $value;
                }

                continue;
            }

            if (preg_match('/^loc(\d+)_name$/', $key, $matches)) {
                $namesByPos[(int) $matches[1]] = $value;
            }
        }

        $idsByPos = array_filter($idsByPos, fn ($id, $pos) => $pos <= $chain->count(), ARRAY_FILTER_USE_BOTH);
        $namesByPos = array_filter($namesByPos, fn ($name, $pos) => $pos <= $chain->count(), ARRAY_FILTER_USE_BOTH);

        return $this->matchLocationById($team, $chain, $idsByPos)
            ?? $this->matchLocationByName($team, $chain, $namesByPos);
    }

    /**
     * @param  Collection<int, LocationLevel>  $chain
     * @param  array<int, int>  $idsByPos
     */
    protected function matchLocationById(Team $team, Collection $chain, array $idsByPos): ?int
    {
        if ($idsByPos === []) {
            return null;
        }

        $deepestPos = max(array_keys($idsByPos));
        $level = $chain->values()->get($deepestPos - 1);

        $location = Location::where('owner_id', $team->id)
            ->where('location_level_id', $level->id)
            ->whereKey($idsByPos[$deepestPos])
            ->first();

        return $location?->id;
    }

    /**
     * @param  Collection<int, LocationLevel>  $chain
     * @param  array<int, string>  $namesByPos
     */
    protected function matchLocationByName(Team $team, Collection $chain, array $namesByPos): ?int
    {
        if ($namesByPos === []) {
            return null;
        }

        $deepestPos = max(array_keys($namesByPos));
        $level = $chain->values()->get($deepestPos - 1);

        $location = Location::where('owner_id', $team->id)
            ->where('location_level_id', $level->id)
            ->whereRaw('LOWER(name) = ?', [Str::lower($namesByPos[$deepestPos])])
            ->first();

        return $location?->id;
    }

    /**
     * Derives the `loc{n}`/`loc{n}_name`/`loc{n}_type` properties Central-side cascading
     * selects (and resolveLocationFromAttributes() on the read side) expect, from a Location
     * the app already resolved - the reverse of resolveLocationFromAttributes(). Walks the
     * Location's parent chain up to the root; `n` is each level's `pos` (1-indexed
     * root-first, matching the Farm Registration XLSForm's own `loc{n}` convention) - not
     * hardcoded to any fixed depth or set of level names, so it works for whatever
     * root-to-leaf chain of LocationLevels a team has configured. `loc{n}_name` is the
     * Location's `name`; `loc{n}_type` is the matched LocationLevel's own `name` (e.g.
     * "Cluster", "District"), not a placeholder.
     *
     * @return array<string, string>
     */
    public function buildLocationAttributes(int $locationId): array
    {
        $location = Location::find($locationId);

        if (! $location) {
            return [];
        }

        $attributes = [];
        $current = $location;

        while ($current) {
            $pos = $current->locationLevel->pos;

            $attributes["loc{$pos}"] = (string) $current->id;
            $attributes["loc{$pos}_name"] = (string) $current->name;
            $attributes["loc{$pos}_type"] = (string) $current->locationLevel->name;
            $current = $current->parent;
        }

        return $attributes;
    }

    /**
     * Builds the `geometry` property value from GPS fields (see GEOMETRY_FIELD doc
     * comment) - the reverse of parseGeometryValue(). Missing altitude/accuracy default to
     * 0, matching how a geopoint widget itself behaves when a device doesn't report them.
     * Returns null (no geometry property at all) if latitude/longitude aren't both given -
     * a bare altitude/accuracy with no coordinate isn't a meaningful point.
     */
    public function buildGeometryValue(?float $latitude, ?float $longitude, ?int $altitude, ?float $accuracy): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return implode(' ', [
            $this->formatGpsFloat($latitude),
            $this->formatGpsFloat($longitude),
            $altitude ?? 0,
            $this->formatGpsFloat($accuracy ?? 0),
        ]);
    }

    /**
     * PHP's plain (string) cast drops the decimal point for a whole-number float (e.g.
     * (string) 45.0 === '45'), which reads as an integer once written into `geometry` -
     * this forces latitude/longitude/accuracy to always render with one, matching the
     * Farm Registration form's own geopoint output (e.g. "45.4215 -75.6972 70.0 4.5" -
     * every component shown with a decimal, including whole-number ones).
     */
    protected function formatGpsFloat(float $value): string
    {
        $formatted = (string) $value;

        return str_contains($formatted, '.') ? $formatted : "{$formatted}.0";
    }

    /**
     * Parses a `geometry` property value (see GEOMETRY_FIELD doc comment) back into the
     * four GPS fields the app's GPS section already uses - the reverse of
     * buildGeometryValue(). Returns all-null if the value isn't at least "latitude
     * longitude" (malformed/unexpected data shouldn't blow up the edit form).
     *
     * @return array{latitude: ?string, longitude: ?string, altitude: ?string, accuracy: ?string}
     */
    public function parseGeometryValue(string $geometry): array
    {
        $parts = preg_split('/\s+/', trim($geometry));

        if (! is_array($parts) || count($parts) < 2) {
            return ['latitude' => null, 'longitude' => null, 'altitude' => null, 'accuracy' => null];
        }

        return [
            'latitude' => $parts[0],
            'longitude' => $parts[1],
            'altitude' => $parts[2] ?? null,
            'accuracy' => $parts[3] ?? null,
        ];
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
     *
     * The location/GPS attributes carried by farms registered directly in Enketo are tagged
     * `'loc'` here, matching how createFarm()/reconcileProperties() tag the same names for
     * app-created farms. Without this they'd default to `'property'` and FarmInfoModuleBuilder
     * would emit `calculate` rows named `loc1`/`loc1_name` etc. that duplicate the Locations
     * module's own fields in the same survey sheet - a duplicate pyxform rejects on deploy.
     */
    protected function ensurePropertyRegistered(Dataset $dataset, string $name): void
    {
        DatasetVariable::firstOrCreate(
            ['dataset_id' => $dataset->id, 'name' => $name],
            ['label' => $name, 'type' => 'string', 'description' => $this->propertyDescription($name)],
        );
    }

    /**
     * The `description` tag a discovered Central property should carry - `'loc'` for the
     * location cascade attributes (`loc{n}`/`loc{n}_name`/`loc{n}_type`) and the GPS
     * properties (the single `geometry` field and the legacy separate GPS_FIELDS), matching
     * how createFarm() tags them, otherwise `'property'`.
     */
    public function propertyDescription(string $name): string
    {
        if (preg_match('/^loc\d+(_name|_type)?$/', $name) === 1) {
            return 'loc';
        }

        if ($name === self::GEOMETRY_FIELD) {
            return 'loc';
        }

        if (in_array($name, self::GPS_FIELDS, true)) {
            return 'loc';
        }

        return 'property';
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

        $dataset = $this->ensureDataset($team);
        $this->ensureOdkDataset($team, $dataset, $entityListName);

        $geometry = $this->buildGeometryValue($latitude, $longitude, $altitude, $accuracy);
        $gpsData = $geometry !== null ? [self::GEOMETRY_FIELD => $geometry] : [];

        $locationAttributes = $this->buildLocationAttributes($locationId);

        $rawData = [...$identifiers, ...$properties, ...$gpsData, ...$locationAttributes, 'team_code' => $teamCode];
        $keyTypes = [
            ...array_fill_keys(array_keys($identifiers), 'identifier'),
            ...array_fill_keys(array_keys($properties), 'property'),
            // GPS is detected by name elsewhere (see GEOMETRY_FIELD doc comment), not by
            // this tag, so a plain 'property' tag is fine here.
            ...array_fill_keys(array_keys($gpsData), 'loc'),
            ...array_fill_keys(array_keys($locationAttributes), 'loc'),
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
     * exists for the team are skipped; a
     * team_code repeated within $rows itself is also deduped, keeping the first occurrence.
     *
     * Each row: locationId (int), teamCode (string), identifiers (array<string, string>),
     * properties (array<string, string>), latitude/longitude (?float), altitude (?int),
     * accuracy (?float). $rows is deliberately left ungenericized in the docblock -
     * Collection's TValue generic isn't covariant, so parameterizing it here rejects any
     * Collection built via a ->map()/->filter() chain even when the shapes are identical.
     *
     * @return Collection<int, FarmEntity>
     */
    public function bulkCreateFarms(Team $team, Collection $rows, ?string $sourceName = null): Collection
    {
        $entityListName = $this->resolveEntityListName($team);

        if ($entityListName === null) {
            throw new \RuntimeException("Team {$team->id} has no active Xlsform with an entities sheet - cannot import farms.");
        }

        $dataset = $this->ensureDataset($team);
        $this->ensureOdkDataset($team, $dataset, $entityListName);

        $existingCodes = FarmEntity::where('owner_id', $team->id)->pluck('team_code')->all();

        $newRows = $rows
            ->reject(fn ($row) => in_array($row['teamCode'], $existingCodes, true))
            ->unique('teamCode')
            ->values();

        if ($newRows->isEmpty()) {
            return collect();
        }

        // Computed once per distinct location - most rows in a batch share a handful of
        // locations, and this saves re-walking the same parent chain per row.
        $locationAttributesByLocationId = $newRows->pluck('locationId')->unique()
            ->mapWithKeys(fn ($locationId) => [$locationId => $this->buildLocationAttributes($locationId)]);

        // GPS varies per row (unlike location, it isn't shared across rows), so it's built
        // once per row here rather than deduped - buildGeometryValue() is pure string
        // formatting, cheap enough not to need memoizing.
        $geometryByRowIndex = $newRows->map(
            fn ($row) => $this->buildGeometryValue($row['latitude'] ?? null, $row['longitude'] ?? null, $row['altitude'] ?? null, $row['accuracy'] ?? null)
        );

        // Reconcile every identifier/property key used across the whole batch up front,
        // rather than once per row.
        $keyTypes = ['team_code' => 'property'];

        foreach ($newRows as $row) {
            $keyTypes = [
                ...$keyTypes,
                ...array_fill_keys(array_keys($row['identifiers']), 'identifier'),
                ...array_fill_keys(array_keys($row['properties']), 'property'),
                ...array_fill_keys(array_keys($locationAttributesByLocationId[$row['locationId']]), 'loc'),
            ];
        }

        if ($geometryByRowIndex->contains(fn ($geometry) => $geometry !== null)) {
            $keyTypes[self::GEOMETRY_FIELD] = 'loc';
        }

        $propertyMap = $this->reconcileProperties($team, $dataset, $entityListName, $keyTypes);

        return DB::transaction(function () use ($team, $entityListName, $newRows, $propertyMap, $locationAttributesByLocationId, $geometryByRowIndex, $sourceName) {

            $prepared = $newRows->map(function ($row, $index) use ($team, $propertyMap, $locationAttributesByLocationId, $geometryByRowIndex) {
                $geometry = $geometryByRowIndex[$index];

                $rawData = [
                    ...$row['identifiers'],
                    ...$row['properties'],
                    ...$locationAttributesByLocationId[$row['locationId']],
                    ...($geometry !== null ? [self::GEOMETRY_FIELD => $geometry] : []),
                    'team_code' => $row['teamCode'],
                ];

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
     * team_code, which has its own dedicated form field. GPS is read from either format: a
     * single GEOMETRY_FIELD property (farms registered directly in Enketo) or the legacy
     * four separate GPS_FIELDS properties (farms created by this app before that format) -
     * both end up in the same four returned GPS keys either way.
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
        $dataset = $this->ensureDataset($team);

        $data = $this->odkLinkService->getOdkEntity($team->odkProject, $entityListName, $farmEntity->odk_uuid)['currentVersion']['data'] ?? [];

        $typeByName = $dataset->variables()->pluck('description', 'name');
        $labelByName = $dataset->variables()->pluck('label', 'name');

        $identifiers = [];
        $properties = [];

        foreach ($data as $name => $value) {
            if ($name === 'team_code') {
                continue;
            }

            if ($name === self::GEOMETRY_FIELD) {
                $gps = $this->parseGeometryValue($value);

                continue;
            }

            if (in_array($name, self::GPS_FIELDS, true)) {
                $gps[$name] = $value;

                continue;
            }

            // Location cascade attributes (loc{n}/loc{n}_name/loc{n}_type) are derived from
            // the farm's resolved Location, not user-editable data - the dedicated Location
            // field owns them. Excluding them here keeps them out of the editable properties
            // UI (and the farm-list columns) and stops them being resubmitted as 'property'.
            if ($this->propertyDescription($name) === 'loc') {
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

        $dataset = $this->ensureDataset($team);
        $this->ensureOdkDataset($team, $dataset, $entityListName);

        // No local mirror of values exists - fetch the entity's current data live to know
        // what it previously had, so anything no longer submitted can be explicitly cleared
        // below (Central's update merges, it doesn't replace - see the comment further down).
        $currentData = $this->odkLinkService->getOdkEntity($team->odkProject, $entityListName, $farmEntity->odk_uuid)['currentVersion']['data'] ?? [];
        $previouslySetNames = array_keys($currentData);

        $geometry = $this->buildGeometryValue($latitude, $longitude, $altitude, $accuracy);
        $gpsData = $geometry !== null ? [self::GEOMETRY_FIELD => $geometry] : [];

        $locationAttributes = $this->buildLocationAttributes($locationId);

        $rawData = [...$identifiers, ...$properties, ...$gpsData, ...$locationAttributes, 'team_code' => $teamCode];
        $keyTypes = [
            ...array_fill_keys(array_keys($identifiers), 'identifier'),
            ...array_fill_keys(array_keys($properties), 'property'),
            // GPS is detected by name elsewhere (see GEOMETRY_FIELD doc comment), not by
            // this tag, so a plain 'loc' tag is fine here.
            ...array_fill_keys(array_keys($gpsData), 'loc'),
            ...array_fill_keys(array_keys($locationAttributes), 'loc'),
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
     * row (not hard-deleted - keeps it around for any future FK references from submission
     * data, once submissions are linked to the farm they describe).
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

        $dataset = $this->ensureDataset($team);
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
        $liveData = $this->extractLiveData($feed);

        // Only one refresh may write for a team at a time. Adopting a few hundred farms
        // that already exist on Central takes ~15s, which is long enough for a second page
        // load (a reload, another tab) to overlap the first - and both walk the same feed,
        // so the second tried to adopt uuids the first had inserted after the second read
        // its local state, dying on the unique odk_uuid constraint mid-way through.
        // Non-blocking on purpose: the run that loses returns the live Central read it has
        // already paid for and leaves the writes to the run holding the lock (whose rows
        // show up on the next load), rather than tying up a worker waiting on it.
        $lock = Cache::lock("farm-entities-refresh:{$team->id}", 120);

        if (! $lock->get()) {
            return $liveData;
        }

        // One transaction for the whole sync: a failure part-way through previously left
        // the team's farm list half-adopted, with no way to tell which rows had made it.
        try {
            DB::transaction(fn () => $this->syncLocalFarms($team, $dataset, $liveData));
        } finally {
            $lock->release();
        }

        return $liveData;
    }

    /**
     * Reduces Central's OData feed to the live data map, dropping rows with no uuid and the
     * OData system/reserved keys. Keying on uuid also collapses a uuid repeated within the
     * feed itself, so the sync can never see the same farm twice in one pass.
     *
     * @param  array<int, array<string, mixed>>  $feed
     * @return array<string, array{label: ?string, data: array<string, string>}>
     */
    protected function extractLiveData(array $feed): array
    {
        $liveData = [];

        foreach ($feed as $row) {
            $uuid = $row['__id'] ?? null;

            if (! $uuid) {
                continue;
            }

            $values = collect($row)->filter(fn ($value, $key) => ! Str::startsWith($key, '__') && ! in_array($key, self::RESERVED_ODATA_KEYS, true));

            $liveData[$uuid] = ['label' => $row['label'] ?? null, 'data' => $values->all()];
        }

        return $liveData;
    }

    /**
     * @param  array<string, array{label: ?string, data: array<string, string>}>  $liveData
     */
    protected function syncLocalFarms(Team $team, Dataset $dataset, array $liveData): void
    {
        $propertyNames = [];

        foreach ($liveData as $uuid => $entry) {
            $this->syncLocalFarm($team, $uuid, $entry['label'], $entry['data']);

            $propertyNames = [...$propertyNames, ...array_keys($entry['data'])];
        }

        // Registered once per distinct property name rather than once per farm - every farm
        // in a feed carries the same handful of property names, so doing this inside the
        // loop above meant a firstOrCreate per property per row.
        foreach (array_unique($propertyNames) as $name) {
            $this->ensurePropertyRegistered($dataset, $name);
        }
    }

    /**
     * @param  array<string, string>  $values
     */
    protected function syncLocalFarm(Team $team, string $uuid, ?string $label, array $values): void
    {
        // Keyed on odk_uuid alone - withTrashed() because the feed only returns Central's
        // currently-active entities, so a uuid in it that we soft-deleted locally was
        // restored on Central and needs restoring here rather than re-inserting; and
        // deliberately not scoped to this team because odk_uuid is globally unique, so a
        // lookup narrowed by owner_id would miss a row held by another team and then
        // collide on insert. Matching on the unique column is also what makes this
        // idempotent - a uuid another run inserted since this run read the feed is found
        // here instead of re-created.
        $farmEntity = FarmEntity::withTrashed()->firstOrNew(['odk_uuid' => $uuid]);

        if (! $farmEntity->exists) {
            // team_code mirrors label bidirectionally - createFarm()/updateFarm() push
            // team_code to Central as label, so adopting the other direction reads it
            // back from label too, not from a same-named property (which may not even
            // exist for entities registered directly in Enketo).
            $farmEntity->fill([
                'owner_id' => $team->id,
                'location_id' => $this->resolveLocationFromAttributes($team, $values),
                'team_code' => (string) ($label ?? $uuid),
            ])->save();

            return;
        }

        if ($farmEntity->owner_id !== $team->id) {
            // Another team already holds this uuid, which shouldn't happen - Central entity
            // uuids are unique across projects. Whatever that row is, its team_code and
            // location belong to that team's feed, not this one's.
            return;
        }

        if ($farmEntity->trashed()) {
            $farmEntity->restore();
        }

        if ($farmEntity->location_id === null) {
            // Adopted before its location attributes were resolvable (or before this
            // feature existed) - worth retrying every refresh until it succeeds.
            $locationId = $this->resolveLocationFromAttributes($team, $values);

            if ($locationId !== null) {
                $farmEntity->update(['location_id' => $locationId]);
            }
        }

        if ($label !== null && $farmEntity->team_code !== $label) {
            // Keeps team_code in sync if the label was ever changed directly on Central
            // (e.g. via Central's own UI) rather than through this app.
            $farmEntity->update(['team_code' => $label]);
        }
    }
}
