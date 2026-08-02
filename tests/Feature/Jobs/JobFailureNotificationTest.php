<?php

use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Concerns\NotifiesOnJobFailure;
use Stats4sd\FilamentOdkLink\Jobs\PrepareSurveyRowPaths;
use Stats4sd\FilamentOdkLink\Jobs\PullSubmissionsFromXlsform;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\DeployDraftXlsformToOdkCentral;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\UpdateXlsformFile;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

function notifierUsingTrait(): object
{
    return new class
    {
        use NotifiesOnJobFailure;
    };
}

function databaseNotificationsFor($user): array
{
    return DB::table('notifications')
        ->where('notifiable_id', $user->id)
        ->get()
        ->map(fn ($row) => json_decode($row->data, true))
        ->all();
}

function createTestXlsform(bool $processing = false): Xlsform
{
    $team = Team::withoutEvents(fn () => Team::factory()->create());

    $template = XlsformTemplate::forceCreateQuietly([
        'title' => 'Notification Template',
    ]);

    return Xlsform::withoutEvents(fn () => Xlsform::create([
        'xlsform_template_id' => $template->id,
        'owner_id' => $team->id,
        'title' => 'Notification Form',
        'processing' => $processing,
    ]));
}

test('notifyJobFailure stores a database notification with title and body for a single user', function () {
    $user = createSuperAdmin();

    notifierUsingTrait()->notifyJobFailure('Something failed', new Exception('it broke'), $user);

    $notifications = databaseNotificationsFor($user);

    expect($notifications)->toHaveCount(1)
        ->and($notifications[0]['title'])->toBe('Something failed')
        ->and($notifications[0]['body'])->toBe('it broke');
});

test('notifyJobFailure handles a null exception', function () {
    $user = createSuperAdmin();

    notifierUsingTrait()->notifyJobFailure('Something failed', null, $user);

    expect(databaseNotificationsFor($user)[0]['body'])->toBe('Unknown error');
});

test('notifyJobFailure truncates long exception messages', function () {
    $user = createSuperAdmin();

    notifierUsingTrait()->notifyJobFailure('Something failed', new Exception(str_repeat('x', 500)), $user);

    expect(strlen(databaseNotificationsFor($user)[0]['body']))->toBeLessThanOrEqual(203);
});

test('notifyJobFailure notifies every user in a collection', function () {
    $userOne = createSuperAdmin();
    $userTwo = createSuperAdmin();

    notifierUsingTrait()->notifyJobFailure('Something failed', new Exception('it broke'), collect([$userOne, $userTwo]));

    expect(databaseNotificationsFor($userOne))->toHaveCount(1)
        ->and(databaseNotificationsFor($userTwo))->toHaveCount(1);
});

test('notifyJobFailure includes an action url when given', function () {
    $user = createSuperAdmin();

    notifierUsingTrait()->notifyJobFailure('Something failed', new Exception('it broke'), $user, 'https://example.test/somewhere');

    $notification = databaseNotificationsFor($user)[0];

    expect($notification['actions'][0]['url'])->toBe('https://example.test/somewhere');
});

test('a failed template import chain job notifies super admins', function () {
    $superAdmin = createSuperAdmin();

    $template = XlsformTemplate::forceCreateQuietly([
        'title' => 'Broken Template',
        'processing' => true,
    ]);

    (new PrepareSurveyRowPaths($template))->failed(new Exception('sheet exploded'));

    $notifications = databaseNotificationsFor($superAdmin);

    expect($notifications)->toHaveCount(1)
        ->and($notifications[0]['title'])->toContain('Broken Template');
});

test('a failed draft deployment notifies the triggering user durably', function () {
    $user = createSuperAdmin();
    $xlsform = createTestXlsform();

    (new DeployDraftXlsformToOdkCentral($xlsform, true, $user))->failed(new Exception('odk rejected the form'));

    $notifications = databaseNotificationsFor($user);

    expect($notifications)->toHaveCount(1)
        ->and($notifications[0]['title'])->toContain('Notification Form');
});

test('a failed draft deployment with no user notifies super admins', function () {
    $superAdmin = createSuperAdmin();
    $xlsform = createTestXlsform();

    (new DeployDraftXlsformToOdkCentral($xlsform, true, null))->failed(new Exception('odk rejected the form'));

    expect(databaseNotificationsFor($superAdmin))->toHaveCount(1);
});

test('a failed submission pull notifies super admins with form and owner context', function () {
    $superAdmin = createSuperAdmin();
    $xlsform = createTestXlsform();

    (new PullSubmissionsFromXlsform($xlsform))->failed(new Exception('unknown form version'));

    $notifications = databaseNotificationsFor($superAdmin);

    expect($notifications)->toHaveCount(1)
        ->and($notifications[0]['title'])->toContain('Notification Form')
        ->and($notifications[0]['title'])->toContain($xlsform->owner->name)
        ->and($notifications[0]['body'])->toBe('unknown form version');
});

test('an xlsform file update with a missing file fails the job instead of continuing silently', function () {
    $superAdmin = createSuperAdmin();
    $xlsform = createTestXlsform(processing: true);

    UpdateXlsformFile::dispatchSync($xlsform, 'temp/does-not-exist.xlsx');

    expect($xlsform->fresh()->processing)->toBeFalsy()
        ->and(databaseNotificationsFor($superAdmin))->toHaveCount(1);
});
