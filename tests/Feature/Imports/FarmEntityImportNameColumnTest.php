<?php

use App\Imports\FarmEntityImport;
use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Services\OdkFarmEntityService;
use Illuminate\Support\Collection;

// The optional "farmer name column" of the farm import: FarmEntityImport::collection() resolves it
// through header_columns, hands each row's name and the column to bulkCreateFarms(), and keeps it
// out of the generic identifier/property lists. See docs/plans/2026-10-29-farm-entity-label-with-name.md.

beforeEach(function () {
    $team = Team::factory()->create();
    $level = LocationLevel::create(['owner_id' => $team->id, 'name' => 'Group', 'has_farms' => true]);
    Location::create(['owner_id' => $team->id, 'location_level_id' => $level->id, 'code' => 'G1', 'name' => 'Group 1']);

    $this->importData = fn (array $overrides = []) => array_merge([
        'header_columns' => ['farm_code' => 'farm_code', 'location_code' => 'location_code', 'farmer_name' => 'farmer_name', 'phone' => 'phone'],
        'farm_code_column' => 'farm_code',
        'location_code_column' => 'location_code',
        'location_level_id' => $level->id,
        'owner_id' => $team->id,
        'farm_identifiers' => ['phone'],
        'farm_properties' => [],
    ], $overrides);

    $this->rows = collect([['farm_code' => '154', 'location_code' => 'G1', 'farmer_name' => 'Elvia Vasquez', 'phone' => '555']]);

    $this->captured = new stdClass;

    $this->mock(OdkFarmEntityService::class, function ($mock) {
        $mock->shouldReceive('bulkCreateFarms')->once()->andReturnUsing(function ($team, Collection $rows, $source, $nameColumn) {
            $this->captured->rows = $rows->all();
            $this->captured->nameColumn = $nameColumn;

            return collect();
        });
    });
});

test('passes the name column and each row\'s name to bulkCreateFarms', function () {
    (new FarmEntityImport(($this->importData)(['farm_name_column' => 'farmer_name'])))->collection($this->rows);

    expect($this->captured->nameColumn)->toBe('farmer_name')
        ->and($this->captured->rows[0]['name'])->toBe('Elvia Vasquez')
        ->and($this->captured->rows[0]['identifiers'])->toBe(['phone' => '555']);
});

test('keeps the name column out of the identifiers even if it was also ticked there', function () {
    (new FarmEntityImport(($this->importData)(['farm_name_column' => 'farmer_name', 'farm_identifiers' => ['phone', 'farmer_name']])))->collection($this->rows);

    expect($this->captured->rows[0]['identifiers'])->toBe(['phone' => '555']);
});

test('without a name column nothing name-related is passed', function (mixed $unset) {
    (new FarmEntityImport(($this->importData)(['farm_name_column' => $unset])))->collection($this->rows);

    expect($this->captured->nameColumn)->toBeNull()
        ->and($this->captured->rows[0]['name'])->toBeNull();
})->with([null, '']);
