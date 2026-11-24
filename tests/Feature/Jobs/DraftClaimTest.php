<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Bus;
use Maatwebsite\Excel\Facades\Excel;
use Stats4sd\FilamentOdkLink\Exports\XlsformExport\XlsformWorkbookExport;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\DeployDraftXlsformToOdkCentral;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\PublishXlsformOnOdkCentral;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\UpdateXlsformFile;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

function createClaimTestXlsform(array $attributes = []): Xlsform
{
    $team = Team::withoutEvents(fn () => Team::factory()->create());

    $template = XlsformTemplate::forceCreateQuietly(['title' => 'Claim Template']);

    return Xlsform::withoutEvents(fn () => Xlsform::create(array_merge([
        'xlsform_template_id' => $template->id,
        'owner_id' => $team->id,
        'title' => 'Claim Form',
    ], $attributes)));
}

function storedXlsform(Xlsform $xlsform): Xlsform
{
    return Xlsform::withoutGlobalScopes()->findOrFail($xlsform->id);
}

function attachXlsformFileRow(Xlsform $xlsform): void
{
    DB::table('media')->insert([
        'model_type' => Xlsform::class,
        'model_id' => $xlsform->id,
        'uuid' => (string) Str::uuid(),
        'collection_name' => 'xlsform_file',
        'name' => 'form',
        'file_name' => 'form.xlsx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'disk' => 'local',
        'size' => 1,
        'manipulations' => '[]',
        'custom_properties' => '[]',
        'generated_conversions' => '[]',
        'responsive_images' => '[]',
        'order_column' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('a form can be claimed by only one caller at a time', function () {
    $xlsform = createClaimTestXlsform();

    expect($xlsform->claimForDeployment())->toBeTrue();
    expect(storedXlsform($xlsform)->claimForDeployment())->toBeFalse();

    $stored = storedXlsform($xlsform);

    expect($stored->processing)->toBeTruthy();
    expect($stored->draft_claimed_at)->not->toBeNull();
});

test('a claim older than the time limit can be taken again', function () {
    $xlsform = createClaimTestXlsform([
        'processing' => true,
        'draft_claimed_at' => now()->subMinutes(Xlsform::CLAIM_TTL_MINUTES + 1),
    ]);

    expect($xlsform->claimForDeployment())->toBeTrue();
});

test('a claim inside the time limit cannot be taken', function () {
    $xlsform = createClaimTestXlsform([
        'processing' => true,
        'draft_claimed_at' => now()->subMinutes(Xlsform::CLAIM_TTL_MINUTES - 1),
    ]);

    expect($xlsform->claimForDeployment())->toBeFalse();
});

test('a form left processing by older code, with no claim time, can be claimed', function () {
    $xlsform = createClaimTestXlsform(['processing' => true]);

    expect($xlsform->claimForDeployment())->toBeTrue();
});

test('edits to the team forms do not extend an old claim', function () {
    $xlsform = createClaimTestXlsform([
        'processing' => true,
        'draft_claimed_at' => now()->subMinutes(Xlsform::CLAIM_TTL_MINUTES + 1),
    ]);

    $this->travel(1)->minutes();

    Xlsform::withoutGlobalScopes()
        ->where('owner_id', $xlsform->owner_id)
        ->update(['draft_needs_update' => true]);

    expect(storedXlsform($xlsform)->claimForDeployment())->toBeTrue();
});

test('releasing a claim clears it and can mark the form as needing an update', function () {
    $xlsform = createClaimTestXlsform(['draft_needs_update' => false]);
    $xlsform->claimForDeployment();

    $xlsform->releaseClaim();

    $stored = storedXlsform($xlsform);
    expect($stored->processing)->toBeFalsy();
    expect($stored->draft_claimed_at)->toBeNull();
    expect($stored->draft_needs_update)->toBeFalsy();

    $xlsform->claimForDeployment();
    $xlsform->releaseClaim(needsUpdate: true);

    expect(storedXlsform($xlsform)->draft_needs_update)->toBeTruthy();
    expect($xlsform->draft_needs_update)->toBeTruthy();
});

test('the draft state of a form', function (array $attributes, string $expectedState, bool $expectedOutdated) {
    $xlsform = createClaimTestXlsform();
    $xlsform->forceFill($attributes);

    expect($xlsform->draftState())->toBe($expectedState);
    expect($xlsform->draftIsOutdated())->toBe($expectedOutdated);
})->with(fn () => [
    'being updated' => [['has_draft' => '1', 'processing' => true, 'draft_claimed_at' => now()], Xlsform::DRAFT_STATE_UPDATING, false],
    'being updated for the first time' => [['has_draft' => '0', 'processing' => true, 'draft_claimed_at' => now()], Xlsform::DRAFT_STATE_UPDATING, true],
    'never deployed' => [['has_draft' => '0'], Xlsform::DRAFT_STATE_MISSING, true],
    'changed since the last update' => [['has_draft' => '1', 'draft_needs_update' => true], Xlsform::DRAFT_STATE_EXPIRED, true],
    'abandoned claim' => [['has_draft' => '1', 'processing' => true, 'draft_claimed_at' => now()->subHour()], Xlsform::DRAFT_STATE_EXPIRED, true],
    'claim from older code' => [['has_draft' => '1', 'processing' => true], Xlsform::DRAFT_STATE_EXPIRED, true],
    'up to date' => [['has_draft' => '1', 'draft_needs_update' => false, 'processing' => false], Xlsform::DRAFT_STATE_CURRENT, false],
]);

test('deploying a draft does nothing when another caller holds the claim', function () {
    Excel::fake();
    $xlsform = createClaimTestXlsform(['processing' => true, 'draft_claimed_at' => now()]);

    expect($xlsform->deployDraft())->toBeNull();
});

test('deploying a draft claims the form, clears the flag and chains the file and deploy jobs', function () {
    Bus::fake();
    fakeWorkbookExportQueue();
    $user = User::factory()->create();
    $xlsform = createClaimTestXlsform(['draft_needs_update' => true]);

    $pendingDeployment = $xlsform->deployDraft(user: $user);

    $stored = storedXlsform($xlsform);
    expect($stored->processing)->toBeTruthy();
    expect($stored->draft_claimed_at)->not->toBeNull();
    expect($stored->draft_needs_update)->toBeFalsy();

    $chain = collect($pendingDeployment->getJob()->chained)
        ->map(fn (string $serialized) => unserialize($serialized))
        ->take(-2)
        ->values();

    expect($chain)->toHaveCount(2);
    expect($chain[0])->toBeInstanceOf(UpdateXlsformFile::class);
    expect($chain[0]->holdClaim)->toBeTrue();
    expect($chain[0]->user->is($user))->toBeTrue();
    expect($chain[1])->toBeInstanceOf(DeployDraftXlsformToOdkCentral::class);
    expect($chain[1]->holdClaim)->toBeFalse();
    expect($chain[1]->snapshotAt)->not->toBeNull();
    expect($chain[1]->user->is($user))->toBeTrue();
});

test('a failure while queueing the draft releases the claim and puts the flag back', function () {
    Excel::shouldReceive('queue')->andThrow(new RuntimeException('queue is down'));
    $xlsform = createClaimTestXlsform(['draft_needs_update' => true]);

    expect(fn () => $xlsform->deployDraft())->toThrow(RuntimeException::class);

    $stored = storedXlsform($xlsform);
    expect($stored->processing)->toBeFalsy();
    expect($stored->draft_claimed_at)->toBeNull();
    expect($stored->draft_needs_update)->toBeTruthy();
});

test('the redeploy after publishing keeps the flag and the snapshot as they are', function () {
    Bus::fake();
    $snapshotAt = now()->subHour();
    $xlsform = createClaimTestXlsform([
        'draft_needs_update' => true,
        'draft_snapshot_at' => $snapshotAt,
        'processing' => true,
        'draft_claimed_at' => now(),
    ]);

    $xlsform->deployDraft(published: true, alreadyClaimed: true);

    Bus::assertDispatched(DeployDraftXlsformToOdkCentral::class, fn ($job) => $job->snapshotAt === null && $job->holdClaim === false);

    $stored = storedXlsform($xlsform);
    expect($stored->draft_needs_update)->toBeTruthy();
    expect($stored->draft_snapshot_at->timestamp)->toBe($snapshotAt->timestamp);
});

test('a successful deploy writes the snapshot, releases the claim and keeps a flag set during the deploy', function () {
    $xlsform = createClaimTestXlsform(['odk_version_id' => 'v1', 'draft_needs_update' => false]);
    attachXlsformFileRow($xlsform);
    $xlsform->claimForDeployment();
    $snapshotAt = now()->subMinutes(2);

    $odkLinkService = Mockery::mock(OdkLinkService::class);
    $odkLinkService->shouldReceive('createDraftForm')->once()->andReturnUsing(function (Xlsform $deployed) {
        // a change saved while the form is being deployed
        Xlsform::withoutGlobalScopes()->whereKey($deployed->id)->update(['draft_needs_update' => true]);

        return $deployed;
    });
    app()->instance(OdkLinkService::class, $odkLinkService);

    Xlsform::withoutEvents(fn () => (new DeployDraftXlsformToOdkCentral($xlsform, true, null, $snapshotAt))->handle());

    $stored = storedXlsform($xlsform);
    expect($stored->processing)->toBeFalsy();
    expect($stored->draft_claimed_at)->toBeNull();
    expect($stored->draft_snapshot_at->timestamp)->toBe($snapshotAt->timestamp);
    expect($stored->draft_needs_update)->toBeTruthy();
});

test('a deploy that is followed by another job keeps the claim', function () {
    $xlsform = createClaimTestXlsform(['odk_version_id' => 'v1']);
    attachXlsformFileRow($xlsform);
    $xlsform->claimForDeployment();

    $odkLinkService = Mockery::mock(OdkLinkService::class);
    $odkLinkService->shouldReceive('createDraftForm')->once()->andReturnUsing(fn (Xlsform $deployed) => $deployed);
    app()->instance(OdkLinkService::class, $odkLinkService);

    Xlsform::withoutEvents(fn () => (new DeployDraftXlsformToOdkCentral($xlsform, true, null, now(), holdClaim: true))->handle());

    expect(storedXlsform($xlsform)->processing)->toBeTruthy();
});

test('a job from before the claim existed still runs and releases the form', function () {
    $xlsform = createClaimTestXlsform(['odk_version_id' => 'v1']);
    attachXlsformFileRow($xlsform);
    $xlsform->claimForDeployment();

    $oldJob = new DeployDraftXlsformToOdkCentral($xlsform, true, null);
    $oldPayload = preg_replace('/;s:\d+:"snapshotAt";N;s:\d+:"holdClaim";b:0/', '', serialize($oldJob));
    $unserialized = unserialize($oldPayload);

    $odkLinkService = Mockery::mock(OdkLinkService::class);
    $odkLinkService->shouldReceive('createDraftForm')->once()->andReturnUsing(fn (Xlsform $deployed) => $deployed);
    app()->instance(OdkLinkService::class, $odkLinkService);

    Xlsform::withoutEvents(fn () => $unserialized->handle());

    expect(storedXlsform($xlsform)->processing)->toBeFalsy();
});

test('a failure at any step of the chain releases the claim and puts the flag back', function (Closure $fail) {
    createSuperAdmin();
    $xlsform = createClaimTestXlsform(['draft_needs_update' => false]);
    $xlsform->claimForDeployment();

    $fail($xlsform);

    $stored = storedXlsform($xlsform);
    expect($stored->processing)->toBeFalsy();
    expect($stored->draft_claimed_at)->toBeNull();
    expect($stored->draft_needs_update)->toBeTruthy();
})->with([
    'the workbook export' => [fn (Xlsform $xlsform) => (new XlsformWorkbookExport($xlsform))->failed(new Exception('boom'))],
    'the file update' => [fn (Xlsform $xlsform) => (new UpdateXlsformFile($xlsform, 'temp/file.xlsx', null, holdClaim: true))->failed(new Exception('boom'))],
    'the deploy' => [fn (Xlsform $xlsform) => (new DeployDraftXlsformToOdkCentral($xlsform, true, null))->failed(new Exception('boom'))],
]);

test('a file update that is part of a deploy chain keeps the claim after it succeeds', function () {
    $xlsform = createClaimTestXlsform();
    $xlsform->claimForDeployment();
    attachXlsformFileRow($xlsform);

    $job = new UpdateXlsformFile($xlsform, 'temp/gone.xlsx', null, holdClaim: true);
    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('fail')->once();
    $job->setJob($queueJob);

    $job->handle();

    expect(storedXlsform($xlsform)->processing)->toBeTruthy();
});

test('publishing takes the claim and chains the draft deploy and the publish', function () {
    Bus::fake();
    fakeWorkbookExportQueue();
    $xlsform = createClaimTestXlsform(['draft_needs_update' => true]);

    $pendingPublish = $xlsform->publishForm();

    expect(storedXlsform($xlsform)->processing)->toBeTruthy();

    $chain = collect($pendingPublish->getJob()->chained)
        ->map(fn (string $serialized) => unserialize($serialized))
        ->take(-3)
        ->values();

    expect($chain[0])->toBeInstanceOf(UpdateXlsformFile::class);
    expect($chain[1])->toBeInstanceOf(DeployDraftXlsformToOdkCentral::class);
    expect($chain[1]->holdClaim)->toBeTrue();
    expect($chain[2])->toBeInstanceOf(PublishXlsformOnOdkCentral::class);
});

test('publishing a form whose draft is current takes the claim and dispatches only the publish', function () {
    Bus::fake();
    $xlsform = createClaimTestXlsform(['draft_needs_update' => false]);

    $xlsform->publishForm();

    Bus::assertDispatched(PublishXlsformOnOdkCentral::class);
    expect(storedXlsform($xlsform)->processing)->toBeTruthy();
});

test('publishing is refused while another caller holds the claim', function () {
    Bus::fake();
    $xlsform = createClaimTestXlsform(['processing' => true, 'draft_claimed_at' => now()]);

    expect($xlsform->publishForm())->toBeNull();
    Bus::assertNothingDispatched();
});

test('the publish job hands its claim to the final draft redeploy', function () {
    Bus::fake();
    $xlsform = createClaimTestXlsform();
    $xlsform->claimForDeployment();

    $odkLinkService = Mockery::mock(OdkLinkService::class);
    $odkLinkService->shouldReceive('publishForm')->once();
    app()->instance(OdkLinkService::class, $odkLinkService);

    Xlsform::withoutEvents(fn () => (new PublishXlsformOnOdkCentral($xlsform, null))->handle());

    Bus::assertDispatched(DeployDraftXlsformToOdkCentral::class, fn ($job) => $job->snapshotAt === null && $job->holdClaim === false);
    expect(storedXlsform($xlsform)->processing)->toBeTruthy();
});
