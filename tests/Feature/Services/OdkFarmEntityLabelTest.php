<?php

use App\Models\SampleFrame\FarmEntity;
use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Services\OdkFarmEntityService;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkDataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;

// Farm entity labels: "ID: {code} / Name: {name}" when the team has a farmer name property,
// otherwise the plain farm code. See docs/plans/2026-10-29-farm-entity-label-with-name.md.

function fakeCentralForLabels(array $entityData = [], array $feed = []): void
{
    // Http::fake() appends to the stubs already registered (the first match wins) and keeps the
    // recorded requests, so a fresh factory is needed to re-fake with different responses.
    Http::swap(new Factory);

    Http::fake([
        '*/sessions' => Http::response(['token' => 'fake-token'], 200),
        '*/properties' => Http::response([], 200),
        '*/datasets/Farm_Summary.svc/Entities' => Http::response(['value' => $feed], 200),
        '*/datasets/Farm_Summary/entities/*' => Http::response(['currentVersion' => ['version' => 2, 'data' => $entityData]], 200),
        '*/datasets/Farm_Summary/entities' => Http::response(['uuid' => 'created-uuid', 'currentVersion' => ['version' => 1]], 200),
    ]);
}

function sentEntityLabels(string $method): array
{
    return Http::recorded()
        ->filter(fn (array $pair) => $pair[0]->method() === $method && str_contains($pair[0]->url(), '/datasets/Farm_Summary/entities'))
        ->map(fn (array $pair) => $pair[0]['label'] ?? collect($pair[0]['entities'])->pluck('label')->all())
        ->values()
        ->all();
}

beforeEach(function () {
    fakeCentralForLabels();

    $this->team = Team::factory()->create();
    $this->project = OdkProject::create(['id' => 1, 'owner_type' => Team::class, 'owner_id' => $this->team->id, 'name' => 'Project 1']);

    $this->service = app(OdkFarmEntityService::class);
    $dataset = $this->service->ensureDataset($this->team);

    OdkDataset::create(['dataset_id' => $dataset->id, 'odk_project_id' => $this->project->id, 'owner_id' => $this->team->id, 'name' => 'Farm_Summary']);

    $level = LocationLevel::create(['owner_id' => $this->team->id, 'name' => 'Group', 'has_farms' => true]);
    $this->location = Location::create(['owner_id' => $this->team->id, 'location_level_id' => $level->id, 'code' => 'G1', 'name' => 'Group 1']);

    $this->row = fn (string $code, ?string $name) => [
        'locationId' => $this->location->id,
        'teamCode' => $code,
        'name' => $name,
        'identifiers' => [],
        'properties' => [],
    ];
});

describe('buildFarmLabel', function () {

    test('combines the code and the name', function () {
        expect(app(OdkFarmEntityService::class)->buildFarmLabel('154', 'Elvia Vasquez'))->toBe('ID: 154 / Name: Elvia Vasquez');
    });

    test('falls back to the code when the name is null, blank or whitespace', function (?string $name) {
        expect(app(OdkFarmEntityService::class)->buildFarmLabel('154', $name))->toBe('154');
    })->with([null, '', '   ', "\n\t"]);

    test('trims the name and collapses inner whitespace and newlines', function () {
        expect(app(OdkFarmEntityService::class)->buildFarmLabel('154', "  Elvia \n  Vasquez "))->toBe('ID: 154 / Name: Elvia Vasquez');
    });

    test('keeps a slash inside the name', function () {
        expect(app(OdkFarmEntityService::class)->buildFarmLabel('154', 'Ana / Luis'))->toBe('ID: 154 / Name: Ana / Luis');
    });

});

describe('bulkCreateFarms', function () {

    test('labels farms with the name column, stores the name as an identifier and remembers the property', function () {
        $rows = collect([($this->row)('154', 'Elvia Vasquez'), ($this->row)('155', 'Sam Roe')]);

        $this->service->bulkCreateFarms($this->team, $rows, 'farms.xlsx', 'farmer_name');

        // sent in reverse order, see OdkFarmEntityServiceBulkCreateOrderTest
        expect(sentEntityLabels('POST'))->toBe([['ID: 155 / Name: Sam Roe', 'ID: 154 / Name: Elvia Vasquez']]);

        $variable = $this->service->ensureDataset($this->team)->variables()->where('label', 'farmer_name')->first();

        expect($variable->name)->toBe('farmer_name')
            ->and($variable->description)->toBe('identifier')
            ->and($this->team->fresh()->farm_name_property)->toBe('farmer_name');
    });

    test('remembers the sanitized property name when the header is a name ODK bans', function () {
        $this->service->bulkCreateFarms($this->team, collect([($this->row)('154', 'Elvia Vasquez')]), null, 'name');

        $property = $this->team->fresh()->farm_name_property;

        expect($property)->not->toBe('name')
            ->and($property)->toStartWith('name_')
            ->and($this->service->ensureDataset($this->team)->variables()->where('name', $property)->exists())->toBeTrue();
    });

    test('falls back to the code for a row with an empty name', function () {
        $this->service->bulkCreateFarms($this->team, collect([($this->row)('154', '')]), null, 'farmer_name');

        expect(sentEntityLabels('POST'))->toBe([['154']]);
    });

    test('keeps the plain code label and leaves the team setting alone without a name column', function () {
        $this->service->bulkCreateFarms($this->team, collect([($this->row)('154', null)]));

        expect(sentEntityLabels('POST'))->toBe([['154']])
            ->and($this->team->fresh()->farm_name_property)->toBeNull();
    });

});

describe('createFarm / updateFarm', function () {

    test('label with the name when the team has a name property', function () {
        $this->team->update(['farm_name_property' => 'farmer_name']);

        $farm = $this->service->createFarm($this->team, $this->location->id, '154', identifiers: ['farmer_name' => 'Elvia Vasquez']);

        expect(sentEntityLabels('POST'))->toBe(['ID: 154 / Name: Elvia Vasquez']);

        fakeCentralForLabels(['farmer_name' => 'Elvia Vasquez', 'team_code' => '154']);

        $this->service->updateFarm($farm->fresh(), $this->location->id, '154', identifiers: ['farmer_name' => 'Elvia V. Rojas']);

        expect(sentEntityLabels('PATCH'))->toBe(['ID: 154 / Name: Elvia V. Rojas']);
    });

    test('a cleared name falls back to the code', function () {
        $this->team->update(['farm_name_property' => 'farmer_name']);

        $farm = $this->service->createFarm($this->team, $this->location->id, '154', identifiers: ['farmer_name' => 'Elvia Vasquez']);

        fakeCentralForLabels(['farmer_name' => 'Elvia Vasquez', 'team_code' => '154']);

        $this->service->updateFarm($farm->fresh(), $this->location->id, '154');

        expect(sentEntityLabels('PATCH'))->toBe(['154']);
    });

    test('label is just the code when the team has no name property', function () {
        $farm = $this->service->createFarm($this->team, $this->location->id, '154', identifiers: ['farmer_name' => 'Elvia Vasquez']);

        expect(sentEntityLabels('POST'))->toBe(['154']);

        fakeCentralForLabels(['farmer_name' => 'Elvia Vasquez', 'team_code' => '154']);

        $this->service->updateFarm($farm->fresh(), $this->location->id, '155', identifiers: ['farmer_name' => 'Elvia Vasquez']);

        expect(sentEntityLabels('PATCH'))->toBe(['155']);
    });

});

describe('refreshFromCentral', function () {

    beforeEach(function () {
        $this->farm = FarmEntity::create(['owner_id' => $this->team->id, 'location_id' => $this->location->id, 'team_code' => '154', 'odk_uuid' => 'uuid-1', 'odk_version' => 1]);
    });

    test('keeps team_code when the team labels with names (the property is the source of truth)', function () {
        $this->team->update(['farm_name_property' => 'farmer_name']);

        fakeCentralForLabels(feed: [['__id' => 'uuid-1', 'label' => 'ID: 154 / Name: Elvia Vasquez', 'team_code' => '154', 'farmer_name' => 'Elvia Vasquez']]);

        $this->service->refreshFromCentral($this->team->fresh());

        expect($this->farm->fresh()->team_code)->toBe('154');
    });

    test('adopts an unknown entity with the team_code property, not the composite label', function () {
        $this->team->update(['farm_name_property' => 'farmer_name']);

        fakeCentralForLabels(feed: [['__id' => 'uuid-9', 'label' => 'ID: 200 / Name: Sam Roe', 'team_code' => '200', 'farmer_name' => 'Sam Roe']]);

        $this->service->refreshFromCentral($this->team->fresh());

        expect(FarmEntity::where('odk_uuid', 'uuid-9')->value('team_code'))->toBe('200');
    });

    test('falls back to the label for an entity with no team_code property', function () {
        $this->team->update(['farm_name_property' => 'farmer_name']);

        fakeCentralForLabels(feed: [['__id' => 'uuid-1', 'label' => '154B']]);

        $this->service->refreshFromCentral($this->team->fresh());

        expect($this->farm->fresh()->team_code)->toBe('154B');
    });

    test('a team without a name property still follows a label edited on Central', function () {
        fakeCentralForLabels(feed: [['__id' => 'uuid-1', 'label' => '154A', 'team_code' => '154']]);

        $this->service->refreshFromCentral($this->team->fresh());

        expect($this->farm->fresh()->team_code)->toBe('154A');
    });

});

describe('relabelFarms', function () {

    beforeEach(function () {
        $this->team->update(['farm_name_property' => 'farmer_name']);

        FarmEntity::create(['owner_id' => $this->team->id, 'location_id' => $this->location->id, 'team_code' => '154', 'odk_uuid' => 'uuid-1', 'odk_version' => 1]);
        FarmEntity::create(['owner_id' => $this->team->id, 'location_id' => $this->location->id, 'team_code' => '155', 'odk_uuid' => 'uuid-2', 'odk_version' => 1]);
        FarmEntity::create(['owner_id' => $this->team->id, 'location_id' => $this->location->id, 'team_code' => '156', 'odk_uuid' => 'uuid-3', 'odk_version' => 1]);

        $this->feed = [
            ['__id' => 'uuid-1', 'label' => '154', 'team_code' => '154', 'farmer_name' => 'Elvia Vasquez', '__system' => ['version' => 3]],
            ['__id' => 'uuid-2', 'label' => '155', 'team_code' => '155', 'farmer_name' => '', '__system' => ['version' => 1]],
            ['__id' => 'uuid-3', 'label' => 'ID: 156 / Name: Sam Roe', 'team_code' => '156', 'farmer_name' => 'Sam Roe', '__system' => ['version' => 1]],
        ];
    });

    test('a dry run reports the changes and writes nothing', function () {
        fakeCentralForLabels(feed: $this->feed);

        $results = collect($this->service->relabelFarms($this->team, 'farmer_name', apply: false))->keyBy('uuid');

        expect($results['uuid-1']['status'])->toBe('update')
            ->and($results['uuid-1']['new'])->toBe('ID: 154 / Name: Elvia Vasquez')
            ->and($results['uuid-2']['status'])->toBe('no_name')
            ->and($results['uuid-3']['status'])->toBe('unchanged')
            ->and(sentEntityLabels('PATCH'))->toBe([]);
    });

    test('apply updates only the mismatched labels, with the feed version as baseVersion', function () {
        fakeCentralForLabels(feed: $this->feed);

        $this->service->relabelFarms($this->team, 'farmer_name', apply: true);

        expect(sentEntityLabels('PATCH'))->toBe(['ID: 154 / Name: Elvia Vasquez']);

        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && str_contains($request->url(), '/entities/uuid-1?baseVersion=3')
            && $request['data'] === ['farmer_name' => 'Elvia Vasquez']);

        expect(FarmEntity::where('odk_uuid', 'uuid-1')->value('odk_version'))->toBe(2);
    });

    test('a failing update is reported and the run continues', function () {
        Http::swap(new Factory);

        Http::fake([
            '*/sessions' => Http::response(['token' => 'fake-token'], 200),
            '*/datasets/Farm_Summary.svc/Entities' => Http::response(['value' => [
                ...$this->feed,
                ['__id' => 'uuid-4', 'label' => '157', 'team_code' => '157', 'farmer_name' => 'Ana', '__system' => ['version' => 1]],
            ]], 200),
            '*/entities/uuid-1*' => Http::response(['message' => 'conflict'], 409),
            '*/entities/uuid-4*' => Http::response(['currentVersion' => ['version' => 2]], 200),
        ]);

        $results = collect($this->service->relabelFarms($this->team, 'farmer_name', apply: true))->keyBy('uuid');

        expect($results['uuid-1']['status'])->toBe('failed')
            ->and($results['uuid-4']['status'])->toBe('updated');
    });

    test('a second run is a no-op once Central has the new labels', function () {
        $feed = collect($this->feed)->map(fn (array $row) => $row['farmer_name'] === '' ? $row : [...$row, 'label' => "ID: {$row['team_code']} / Name: {$row['farmer_name']}"])->all();

        fakeCentralForLabels(feed: $feed);

        $results = $this->service->relabelFarms($this->team, 'farmer_name', apply: true);

        expect(collect($results)->pluck('status')->unique()->sort()->values()->all())->toBe(['no_name', 'unchanged'])
            ->and(sentEntityLabels('PATCH'))->toBe([]);
    });

});
