<?php

use App\Models\Team;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Exceptions\XlsformFileSupersededException;
use Stats4sd\FilamentOdkLink\Exceptions\XlsformOwnerMissingException;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\DeployDraftXlsformToOdkCentral;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\UpdateXlsformFile;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

function guardedXlsform(): Xlsform
{
    $team = Team::withoutEvents(fn () => Team::factory()->create());

    $template = XlsformTemplate::forceCreateQuietly(['title' => 'Guard Template']);

    return Xlsform::withoutEvents(fn () => Xlsform::create([
        'xlsform_template_id' => $template->id,
        'owner_id' => $team->id,
        'title' => 'Guarded Form',
        'processing' => true,
    ]));
}

function jobThatMustFailWith(string $exceptionClass): QueueJob
{
    $queueJob = Mockery::mock(QueueJob::class);
    $queueJob->shouldReceive('fail')
        ->once()
        ->withArgs(fn ($exception) => $exception instanceof $exceptionClass);

    return $queueJob;
}

function attachFileTo(Xlsform $xlsform, $createdAt): void
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
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

test('deploying a form whose team was deleted fails once without calling ODK Central', function () {
    $xlsform = guardedXlsform();
    $xlsform->owner->delete();

    Http::fake();

    $job = new DeployDraftXlsformToOdkCentral(Xlsform::find($xlsform->id), true, null);
    $job->setJob(jobThatMustFailWith(XlsformOwnerMissingException::class));

    $job->handle();

    Http::assertNothingSent();
});

test('a deploy that failed because the team was deleted notifies nobody', function () {
    $superAdmin = createSuperAdmin();
    $xlsform = guardedXlsform();

    (new DeployDraftXlsformToOdkCentral($xlsform, true, $superAdmin))
        ->failed(new XlsformOwnerMissingException('team deleted'));

    expect(DB::table('notifications')->count())->toBe(0);
});

test('a file update that finds its file already taken by an identical job fails quietly and leaves the form processing', function () {
    $superAdmin = createSuperAdmin();
    $xlsform = guardedXlsform();
    attachFileTo($xlsform, now()->subSeconds(10));

    $job = new UpdateXlsformFile($xlsform, 'temp/gone.xlsx', $superAdmin);
    $job->setJob(jobThatMustFailWith(XlsformFileSupersededException::class));

    $job->handle();
    $job->failed(new XlsformFileSupersededException('gone'));

    expect($xlsform->fresh()->processing)->toBeTruthy();
    expect(DB::table('notifications')->count())->toBe(0);
});

test('a file update with a missing file and no recent file still fails and notifies', function () {
    $superAdmin = createSuperAdmin();
    $xlsform = guardedXlsform();
    attachFileTo($xlsform, now()->subHour());

    $job = new UpdateXlsformFile($xlsform, 'temp/gone.xlsx', $superAdmin);
    $queueJob = Mockery::mock(QueueJob::class);
    $queueJob->shouldReceive('fail')->once()->withArgs(fn ($exception) => ! $exception instanceof XlsformFileSupersededException);
    $job->setJob($queueJob);

    $job->handle();

    expect($xlsform->fresh()->processing)->toBeFalsy();
});
