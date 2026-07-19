<?php

use App\Models\SampleFrame\FarmEntity;
use App\Models\Team;
use App\Services\OdkFarmEntityService;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\DatasetVariable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkDataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;

// Guards the 'loc' classification that keeps the location cascade attributes
// (loc{n}/loc{n}_name/loc{n}_type) and GPS (geometry + legacy GPS_FIELDS) out of the
// user-editable properties/identifiers on the read side, and out of the deploy-time
// FarmInfoModuleBuilder calculate rows on the registration side. See code review F3 + F5
// (docs/code-reviews/2026-07-19-map-location-to-entity-list-attribute.md): both share the
// single classifier OdkFarmEntityService::propertyDescription().

function odkFarmEntityPropertyService(): OdkFarmEntityService
{
    return app(OdkFarmEntityService::class);
}

// OdkProject's primary key is non-incrementing (mirrors ODK Central's own project id), so it
// must be assigned explicitly rather than left to a factory.
function makeOdkProjectForPropertyTest(Team $team, int $id): OdkProject
{
    return OdkProject::create([
        'id' => $id,
        'owner_type' => Team::class,
        'owner_id' => $team->id,
        'name' => "Project {$id}",
    ]);
}

describe('OdkFarmEntityService::propertyDescription', function () {

    test('classifies the location cascade attributes as loc', function () {
        $service = odkFarmEntityPropertyService();

        expect($service->propertyDescription('loc1'))->toBe('loc')
            ->and($service->propertyDescription('loc2'))->toBe('loc')
            ->and($service->propertyDescription('loc10'))->toBe('loc')
            ->and($service->propertyDescription('loc1_name'))->toBe('loc')
            ->and($service->propertyDescription('loc3_type'))->toBe('loc');
    });

    test('classifies the geometry and legacy GPS fields as loc', function () {
        $service = odkFarmEntityPropertyService();

        expect($service->propertyDescription(OdkFarmEntityService::GEOMETRY_FIELD))->toBe('loc');

        foreach (OdkFarmEntityService::GPS_FIELDS as $gpsField) {
            expect($service->propertyDescription($gpsField))->toBe('loc');
        }
    });

    test('classifies everything else as property', function () {
        $service = odkFarmEntityPropertyService();

        expect($service->propertyDescription('household_head'))->toBe('property')
            ->and($service->propertyDescription('team_code'))->toBe('property')
            // Not a cascade attribute despite the substring - only loc{digits}[_name|_type].
            ->and($service->propertyDescription('location_of_water'))->toBe('property')
            ->and($service->propertyDescription('loc'))->toBe('property')
            ->and($service->propertyDescription('loc1_extra'))->toBe('property');
    });

});

describe('OdkFarmEntityService::getEntityData', function () {

    test('excludes the location cascade attributes from the editable identifiers/properties', function () {
        $team = Team::factory()->create();
        makeOdkProjectForPropertyTest($team, 1);

        $service = odkFarmEntityPropertyService();
        $dataset = $service->ensureDataset($team);

        DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'farm_name', 'label' => 'Farm name', 'type' => 'string', 'description' => 'identifier']);
        DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'household_head', 'label' => 'Household head', 'type' => 'string', 'description' => 'property']);

        $farmEntity = FarmEntity::create([
            'owner_id' => $team->id,
            'location_id' => null,
            'team_code' => 'FARM-1',
            'odk_uuid' => 'uuid-1',
        ]);

        Http::fake([
            '*/sessions' => Http::response(['token' => 'fake-token'], 200),
            '*/datasets/Farm_Summary/entities/*' => Http::response([
                'currentVersion' => [
                    'data' => [
                        'team_code' => 'FARM-1',
                        'geometry' => '45.4215 -75.6972 70.0 4.5',
                        'loc1' => '12',
                        'loc1_name' => 'Northern Region',
                        'loc1_type' => 'Region',
                        'farm_name' => 'Green Acres',
                        'household_head' => 'Alex Doe',
                    ],
                ],
            ], 200),
        ]);

        $data = $service->getEntityData($farmEntity);

        expect($data['identifiers'])->toBe(['Farm name' => 'Green Acres'])
            ->and($data['properties'])->toBe(['Household head' => 'Alex Doe'])
            ->and($data['latitude'])->toBe('45.4215')
            ->and($data['longitude'])->toBe('-75.6972');

        $flatKeys = array_merge(array_keys($data['identifiers']), array_keys($data['properties']));

        expect($flatKeys)->not->toContain('loc1')
            ->and($flatKeys)->not->toContain('loc1_name')
            ->and($flatKeys)->not->toContain('loc1_type');
    });

});

describe('OdkFarmEntityService::refreshFromCentral', function () {

    test('registers discovered cascade/GPS attributes with the loc description, not property', function () {
        $team = Team::factory()->create();
        $project = makeOdkProjectForPropertyTest($team, 1);

        $service = odkFarmEntityPropertyService();
        $dataset = $service->ensureDataset($team);

        // Pre-create the local OdkDataset bookkeeping so findOdkDataset() resolves without a
        // Central round trip - the feed itself is faked below.
        OdkDataset::create([
            'dataset_id' => $dataset->id,
            'odk_project_id' => $project->id,
            'owner_id' => $team->id,
            'name' => 'Farm_Summary',
        ]);

        Http::fake([
            '*/sessions' => Http::response(['token' => 'fake-token'], 200),
            '*/datasets/Farm_Summary.svc/Entities' => Http::response([
                'value' => [[
                    '__id' => 'uuid-1',
                    'label' => 'FARM-1',
                    'loc1' => '12',
                    'loc1_name' => 'Northern Region',
                    'loc1_type' => 'Region',
                    'geometry' => '45.4215 -75.6972 70.0 4.5',
                    'household_head' => 'Alex Doe',
                ]],
            ], 200),
        ]);

        $service->refreshFromCentral($team);

        $descriptions = $dataset->variables()->pluck('description', 'name');

        expect($descriptions['loc1'])->toBe('loc')
            ->and($descriptions['loc1_name'])->toBe('loc')
            ->and($descriptions['loc1_type'])->toBe('loc')
            ->and($descriptions['household_head'])->toBe('property')
            // geometry is a reserved OData key stripped from the feed before registration -
            // it never becomes a DatasetVariable via the refresh path.
            ->and($descriptions->has('geometry'))->toBeFalse();
    });

});
