<?php

use App\Jobs\DeployTeamDraftForms;
use App\Models\Team;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Jobs\ExtendedQueueable;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\DeployDraftXlsformToOdkCentral;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

function createTeamXlsform(Team $team, string $title, array $attributes = []): Xlsform
{
    $template = XlsformTemplate::forceCreateQuietly(['title' => "{$title} template"]);

    return Xlsform::withoutEvents(fn () => Xlsform::create(array_merge([
        'xlsform_template_id' => $template->id,
        'owner_id' => $team->id,
        'title' => $title,
        'has_draft' => '1',
        'draft_needs_update' => true,
    ], $attributes)));
}

function storedTeamXlsform(Xlsform $xlsform): Xlsform
{
    return Xlsform::withoutGlobalScopes()->findOrFail($xlsform->id);
}

beforeEach(function () {
    $this->team = Team::withoutEvents(fn () => Team::factory()->create());
    $this->user = createAppUser($this->team);
});

test('the team claims only the forms that are outdated and not already claimed', function () {
    $outdated = createTeamXlsform($this->team, 'Outdated');
    $current = createTeamXlsform($this->team, 'Current', ['draft_needs_update' => false]);
    $updating = createTeamXlsform($this->team, 'Updating', ['processing' => true, 'draft_claimed_at' => now()]);
    $abandoned = createTeamXlsform($this->team, 'Abandoned', ['draft_needs_update' => false, 'processing' => true, 'draft_claimed_at' => now()->subHour()]);

    $claimed = $this->team->claimDraftForms();

    expect($claimed->pluck('id')->sort()->values()->all())->toBe(collect([$outdated->id, $abandoned->id])->sort()->values()->all());
    expect(storedTeamXlsform($outdated)->hasActiveClaim())->toBeTrue();
    expect(storedTeamXlsform($current)->processing)->toBeFalsy();
});

test('the job starts the update of every claimed form', function () {
    Bus::fake();
    fakeWorkbookExportQueue();
    $first = createTeamXlsform($this->team, 'First');
    $second = createTeamXlsform($this->team, 'Second');
    $claimed = $this->team->claimDraftForms();

    (new DeployTeamDraftForms($this->team, $claimed->pluck('id')->all(), $this->user))->handle();

    foreach ([$first, $second] as $xlsform) {
        $stored = storedTeamXlsform($xlsform);
        expect($stored->hasActiveClaim())->toBeTrue();
        expect($stored->draft_needs_update)->toBeFalsy();
    }
});

test('a form that cannot be started is released and the other forms still start', function () {
    Bus::fake();
    $attempt = 0;
    Excel::shouldReceive('queue')->twice()->andReturnUsing(function () use (&$attempt) {
        $attempt++;

        if ($attempt === 1) {
            throw new RuntimeException('could not queue the export');
        }

        return new PendingDispatch(new class
        {
            use ExtendedQueueable;

            public function handle(): void {}
        });
    });
    $first = createTeamXlsform($this->team, 'First');
    $second = createTeamXlsform($this->team, 'Second');
    $claimed = $this->team->claimDraftForms();

    (new DeployTeamDraftForms($this->team, $claimed->pluck('id')->all(), $this->user))->handle();

    $storedFirst = storedTeamXlsform($first);
    expect($storedFirst->processing)->toBeFalsy();
    expect($storedFirst->draft_claimed_at)->toBeNull();
    expect($storedFirst->draft_needs_update)->toBeTruthy();

    $storedSecond = storedTeamXlsform($second);
    expect($storedSecond->hasActiveClaim())->toBeTrue();
    expect($storedSecond->draft_needs_update)->toBeFalsy();

    Bus::assertDispatched(SendQueuedNotifications::class);
});

test('when the team modules cannot be prepared every claim is released and nothing is queued', function () {
    Bus::fake();
    $first = createTeamXlsform($this->team, 'First');
    $second = createTeamXlsform($this->team, 'Second');
    $claimed = $this->team->claimDraftForms();

    $team = Mockery::mock($this->team)->makePartial();
    $team->shouldReceive('localiseXlsforms')->andThrow(new RuntimeException('could not build the modules'));

    (new DeployTeamDraftForms($team, $claimed->pluck('id')->all(), $this->user))->handle();

    foreach ([$first, $second] as $xlsform) {
        $stored = storedTeamXlsform($xlsform);
        expect($stored->processing)->toBeFalsy();
        expect($stored->draft_claimed_at)->toBeNull();
        expect($stored->draft_needs_update)->toBeTruthy();
    }

    Bus::assertNotDispatched(DeployDraftXlsformToOdkCentral::class);
    Bus::assertDispatched(SendQueuedNotifications::class);
});

test('a job that did not finish tells the user and releases nothing', function () {
    $xlsform = createTeamXlsform($this->team, 'First', ['draft_needs_update' => false]);
    $xlsform->claimForDeployment();

    (new DeployTeamDraftForms($this->team, [$xlsform->id], $this->user))->failed(new RuntimeException('timed out'));

    expect(storedTeamXlsform($xlsform)->hasActiveClaim())->toBeTrue();
    expect(DB::table('notifications')->where('notifiable_id', $this->user->id)->count())->toBe(1);
});
