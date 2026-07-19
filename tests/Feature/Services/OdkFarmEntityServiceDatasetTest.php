<?php

use App\Models\Team;
use App\Services\OdkFarmEntityService;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;

// Regression coverage for the team-scoped Dataset fix (see
// docs/plans/team-scoped-farm-entities-dataset.md). Before the fix, ensureDataset() returned
// one Dataset row shared by every team (owner_id null), so reconcileProperties() treated a
// property as "already known" the moment ANY team had used that raw key, and silently skipped
// pushing it to Central for every other team - which then rejected entities referencing that
// property with a 400 "does not exist" error. ensureDataset() is now scoped per team
// (owner_id = $team->id), so each team's bookkeeping - and the Central push it gates - is
// independent.

function odkFarmEntityServiceForDatasetTest(): OdkFarmEntityService
{
    return app(OdkFarmEntityService::class);
}

// OdkProject's primary key is non-incrementing (mirrors ODK Central's own project id), so it
// must be assigned explicitly rather than left to a factory.
function makeOdkProjectFor(Team $team, int $id): OdkProject
{
    return OdkProject::create([
        'id' => $id,
        'owner_type' => Team::class,
        'owner_id' => $team->id,
        'name' => "Project {$id}",
    ]);
}

beforeEach(function () {
    Http::fake([
        '*/sessions' => Http::response(['token' => 'fake-token'], 200),
        '*/properties' => Http::response([], 200),
    ]);
});

test('ensureDataset() returns a separate Dataset row per team', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    $service = odkFarmEntityServiceForDatasetTest();

    $datasetA = $service->ensureDataset($teamA);
    $datasetB = $service->ensureDataset($teamB);

    expect($datasetA->id)->not->toBe($datasetB->id)
        ->and($datasetA->owner_id)->toBe($teamA->id)
        ->and($datasetB->owner_id)->toBe($teamB->id);
});

test('reconcileProperties() pushes a new property to Central separately for each team, even when they share the same raw key', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $projectA = makeOdkProjectFor($teamA, 1);
    $projectB = makeOdkProjectFor($teamB, 2);

    $service = odkFarmEntityServiceForDatasetTest();

    $datasetA = $service->ensureDataset($teamA);
    $datasetB = $service->ensureDataset($teamB);

    // The bug: with a shared Dataset, the second call below would find "participant_sex"
    // already registered (by the first) and skip pushing it to Central entirely.
    $service->reconcileProperties($teamA, $datasetA, 'Farm_Summary', ['participant_sex' => 'property']);
    $service->reconcileProperties($teamB, $datasetB, 'Farm_Summary', ['participant_sex' => 'property']);

    // 1 shared /sessions call (token is cached across both) + one /properties push per team.
    Http::assertSentCount(3);

    Http::assertSent(fn ($request) => str_contains($request->url(), "/projects/{$projectA->id}/datasets/Farm_Summary/properties"));
    Http::assertSent(fn ($request) => str_contains($request->url(), "/projects/{$projectB->id}/datasets/Farm_Summary/properties"));
});

test('reconcileProperties() does not re-push a property already known for that same team', function () {
    $team = Team::factory()->create();
    makeOdkProjectFor($team, 1);

    $service = odkFarmEntityServiceForDatasetTest();
    $dataset = $service->ensureDataset($team);

    $service->reconcileProperties($team, $dataset, 'Farm_Summary', ['participant_sex' => 'property']);
    $service->reconcileProperties($team, $dataset, 'Farm_Summary', ['participant_sex' => 'property']);

    // 1 /sessions call + exactly 1 /properties push - the second reconcileProperties() call
    // finds "participant_sex" already known for this team and must not push again.
    Http::assertSentCount(2);
});
