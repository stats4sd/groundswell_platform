<?php

use App\Models\SampleFrame\FarmEntity;
use App\Models\Team;
use App\Services\OdkFarmEntityService;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\DatasetVariable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkDataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;

// odk:backfill-farm-labels: dry run by default, --apply writes, the team's name property must be a
// property of its farm dataset. The relabelling itself is covered in OdkFarmEntityLabelTest.

beforeEach(function () {
    Http::swap(new Factory);
    Http::fake([
        '*/sessions' => Http::response(['token' => 'fake-token'], 200),
        '*/datasets/Farm_Summary.svc/Entities' => Http::response(['value' => [
            ['__id' => 'uuid-1', 'label' => '154', 'team_code' => '154', 'farmer_name' => 'Elvia Vasquez', '__system' => ['version' => 1]],
        ]], 200),
        '*/datasets/Farm_Summary/entities/*' => Http::response(['currentVersion' => ['version' => 2]], 200),
    ]);

    $this->team = Team::factory()->create();
    $project = OdkProject::create(['id' => 1, 'owner_type' => Team::class, 'owner_id' => $this->team->id, 'name' => 'Project 1']);

    $dataset = app(OdkFarmEntityService::class)->ensureDataset($this->team);
    OdkDataset::create(['dataset_id' => $dataset->id, 'odk_project_id' => $project->id, 'owner_id' => $this->team->id, 'name' => 'Farm_Summary']);
    DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'farmer_name', 'label' => 'farmer_name', 'type' => 'string', 'description' => 'identifier']);

    FarmEntity::create(['owner_id' => $this->team->id, 'team_code' => '154', 'odk_uuid' => 'uuid-1', 'odk_version' => 1]);
});

test('a dry run writes nothing, not even the team setting', function () {
    $this->artisan('odk:backfill-farm-labels', ['--team' => [$this->team->id], '--name-property' => 'farmer_name'])
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('1 would be updated')
        ->assertSuccessful();

    Http::assertNotSent(fn ($request) => $request->method() === 'PATCH');

    expect($this->team->fresh()->farm_name_property)->toBeNull();
});

test('--apply relabels the farm and saves the name property on the team', function () {
    $this->artisan('odk:backfill-farm-labels', ['--team' => [$this->team->id], '--name-property' => 'farmer_name', '--apply' => true])
        ->expectsOutputToContain('1 updated')
        ->assertSuccessful();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['label'] === 'ID: 154 / Name: Elvia Vasquez');

    expect($this->team->fresh()->farm_name_property)->toBe('farmer_name');
});

test('skips a team with no name property', function () {
    $this->artisan('odk:backfill-farm-labels', ['--team' => [$this->team->id], '--apply' => true])
        ->expectsOutputToContain('no farmer name property')
        ->assertSuccessful();

    Http::assertNotSent(fn ($request) => $request->method() === 'PATCH');
});

test('rejects a name property that is not in the team\'s farm dataset', function () {
    $this->artisan('odk:backfill-farm-labels', ['--team' => [$this->team->id], '--name-property' => 'nope', '--apply' => true])
        ->expectsOutputToContain("'nope' is not a property")
        ->assertSuccessful();

    Http::assertNotSent(fn ($request) => $request->method() === 'PATCH');

    expect($this->team->fresh()->farm_name_property)->toBeNull();
});
