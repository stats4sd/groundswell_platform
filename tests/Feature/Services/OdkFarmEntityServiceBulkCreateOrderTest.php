<?php

use App\Models\SampleFrame\FarmEntity;
use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Services\OdkFarmEntityService;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkDataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;

// Central serves an entity list newest first, and a form's select_one_from_file lists the
// choices in that order, so bulkCreateFarms() sends the batch in reverse to make the form show
// the farms in the order of the uploaded file.

beforeEach(function () {
    Http::fake([
        '*/sessions' => Http::response(['token' => 'fake-token'], 200),
        '*/properties' => Http::response([], 200),
        '*/datasets/Farm_Summary/entities' => Http::response(['success' => true], 200),
    ]);

    $this->team = Team::factory()->create();

    $project = OdkProject::create([
        'id' => 1,
        'owner_type' => Team::class,
        'owner_id' => $this->team->id,
        'name' => 'Project 1',
    ]);

    $service = app(OdkFarmEntityService::class);
    $dataset = $service->ensureDataset($this->team);

    OdkDataset::create([
        'dataset_id' => $dataset->id,
        'odk_project_id' => $project->id,
        'owner_id' => $this->team->id,
        'name' => 'Farm_Summary',
    ]);

    $level = LocationLevel::create(['owner_id' => $this->team->id, 'name' => 'Group', 'has_farms' => true]);

    $this->location = Location::create([
        'owner_id' => $this->team->id,
        'location_level_id' => $level->id,
        'code' => 'G1',
        'name' => 'Group 1',
    ]);
});

function bulkFarmRow(int $locationId, string $teamCode): array
{
    return [
        'locationId' => $locationId,
        'teamCode' => $teamCode,
        'identifiers' => [],
        'properties' => [],
    ];
}

test('bulkCreateFarms() sends the entities to Central in reverse of the uploaded order', function () {
    $rows = collect(['F001', 'F002', 'F003'])->map(fn (string $code) => bulkFarmRow($this->location->id, $code));

    app(OdkFarmEntityService::class)->bulkCreateFarms($this->team, $rows, 'farms.xlsx');

    Http::assertSent(function ($request) {
        if (! str_ends_with($request->url(), '/datasets/Farm_Summary/entities')) {
            return false;
        }

        return collect($request['entities'])->pluck('label')->all() === ['F003', 'F002', 'F001'];
    });
});

test('bulkCreateFarms() still creates and returns the local farm records in the uploaded order', function () {
    $rows = collect(['F001', 'F002', 'F003'])->map(fn (string $code) => bulkFarmRow($this->location->id, $code));

    $farmEntities = app(OdkFarmEntityService::class)->bulkCreateFarms($this->team, $rows, 'farms.xlsx');

    expect($farmEntities->pluck('team_code')->all())->toBe(['F001', 'F002', 'F003']);
    expect(FarmEntity::where('owner_id', $this->team->id)->orderBy('id')->pluck('team_code')->all())->toBe(['F001', 'F002', 'F003']);
});
