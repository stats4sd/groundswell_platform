<?php

use App\Models\SampleFrame\FarmEntity;
use App\Models\Team;
use App\Services\OdkFarmEntityService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkDataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;

// Guards refreshFromCentral()'s adoption of entities that exist on Central but not locally
// (farms uploaded to Central outside the platform) against the duplicate-odk_uuid failure it
// used to hit: two overlapping refreshes for the same team both walked the same feed, and the
// second re-inserted uuids the first had created after the second read its local state.

function fakeEntitiesFeed(array $rows): void
{
    Http::fake([
        '*/sessions' => Http::response(['token' => 'fake-token'], 200),
        '*/datasets/Farm_Summary.svc/Entities' => Http::response(['value' => $rows], 200),
    ]);
}

beforeEach(function () {
    $this->team = Team::factory()->create();
    $project = OdkProject::create(['id' => 1, 'owner_type' => Team::class, 'owner_id' => $this->team->id, 'name' => 'Project 1']);

    $this->service = app(OdkFarmEntityService::class);
    $dataset = $this->service->ensureDataset($this->team);

    OdkDataset::create([
        'dataset_id' => $dataset->id,
        'odk_project_id' => $project->id,
        'owner_id' => $this->team->id,
        'name' => 'Farm_Summary',
    ]);

    $this->teamFarms = fn () => FarmEntity::withTrashed()->where('owner_id', $this->team->id);
});

test('adopts entities from the feed that have no local farm yet', function () {
    fakeEntitiesFeed([
        ['__id' => 'uuid-1', 'label' => 'FARM-1', 'household_head' => 'Alex Doe'],
        ['__id' => 'uuid-2', 'label' => 'FARM-2', 'household_head' => 'Sam Roe'],
    ]);

    $liveData = $this->service->refreshFromCentral($this->team);

    expect(array_keys($liveData))->toBe(['uuid-1', 'uuid-2'])
        ->and(($this->teamFarms)()->pluck('team_code', 'odk_uuid')->all())
        ->toBe(['uuid-1' => 'FARM-1', 'uuid-2' => 'FARM-2']);
});

test('adopting is idempotent - a second refresh over the same feed creates nothing new', function () {
    fakeEntitiesFeed([
        ['__id' => 'uuid-1', 'label' => 'FARM-1'],
        ['__id' => 'uuid-2', 'label' => 'FARM-2'],
    ]);

    $this->service->refreshFromCentral($this->team);
    $this->service->refreshFromCentral($this->team);

    expect(($this->teamFarms)()->count())->toBe(2);
});

test('adopts a uuid the feed repeats only once', function () {
    fakeEntitiesFeed([
        ['__id' => 'uuid-1', 'label' => 'FARM-1'],
        ['__id' => 'uuid-1', 'label' => 'FARM-1'],
    ]);

    $this->service->refreshFromCentral($this->team);

    expect(($this->teamFarms)()->count())->toBe(1);
});

test('adopts a uuid whose local farm was created by a concurrent refresh mid-pass', function () {
    fakeEntitiesFeed([
        ['__id' => 'uuid-1', 'label' => 'FARM-1'],
    ]);

    FarmEntity::create([
        'owner_id' => $this->team->id,
        'team_code' => 'FARM-1',
        'odk_uuid' => 'uuid-1',
    ]);

    $this->service->refreshFromCentral($this->team);

    expect(($this->teamFarms)()->count())->toBe(1);
});

test('restores a locally soft-deleted farm whose entity is active on Central again', function () {
    fakeEntitiesFeed([
        ['__id' => 'uuid-1', 'label' => 'FARM-1'],
    ]);

    $farmEntity = FarmEntity::create([
        'owner_id' => $this->team->id,
        'team_code' => 'FARM-1',
        'odk_uuid' => 'uuid-1',
    ]);
    $farmEntity->delete();

    $this->service->refreshFromCentral($this->team);

    expect($farmEntity->fresh()->trashed())->toBeFalse();
});

test('leaves a uuid held by another team untouched', function () {
    fakeEntitiesFeed([
        ['__id' => 'uuid-1', 'label' => 'FARM-1'],
    ]);

    $otherTeam = Team::factory()->create();
    $otherTeamFarm = FarmEntity::create([
        'owner_id' => $otherTeam->id,
        'team_code' => 'OTHER-1',
        'odk_uuid' => 'uuid-1',
    ]);

    $this->service->refreshFromCentral($this->team);

    expect($otherTeamFarm->fresh()->only(['owner_id', 'team_code']))
        ->toBe(['owner_id' => $otherTeam->id, 'team_code' => 'OTHER-1'])
        ->and(($this->teamFarms)()->count())->toBe(0);
});

test('skips the local sync while another refresh holds the team lock, still returning the live read', function () {
    fakeEntitiesFeed([
        ['__id' => 'uuid-1', 'label' => 'FARM-1'],
    ]);

    Cache::lock("farm-entities-refresh:{$this->team->id}", 120)->get();

    $liveData = $this->service->refreshFromCentral($this->team);

    expect(array_keys($liveData))->toBe(['uuid-1'])
        ->and(($this->teamFarms)()->count())->toBe(0);
});

test('releases the team lock once the sync is done', function () {
    fakeEntitiesFeed([
        ['__id' => 'uuid-1', 'label' => 'FARM-1'],
    ]);

    $this->service->refreshFromCentral($this->team);

    expect(Cache::lock("farm-entities-refresh:{$this->team->id}", 120)->get())->toBeTrue();
});

test('rolls the whole sync back when one farm fails part-way through', function () {
    fakeEntitiesFeed([
        ['__id' => 'uuid-1', 'label' => 'FARM-1'],
        ['__id' => 'uuid-2', 'label' => 'FARM-2'],
        ['__id' => 'uuid-3', 'label' => 'FARM-3'],
    ]);

    FarmEntity::created(function (FarmEntity $farmEntity) {
        if ($farmEntity->odk_uuid === 'uuid-2') {
            throw new RuntimeException('boom');
        }
    });

    expect(fn () => $this->service->refreshFromCentral($this->team))->toThrow(RuntimeException::class);

    expect(($this->teamFarms)()->count())->toBe(0)
        ->and(Cache::lock("farm-entities-refresh:{$this->team->id}", 120)->get())->toBeTrue();
});
