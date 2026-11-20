<?php

use App\Filament\App\Pages\PlaceAdaptations\InitialPilot;
use App\Jobs\DeployTeamDraftForms;
use App\Models\Team;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

use function Pest\Livewire\livewire;

function createPilotXlsform(Team $team, string $title, array $attributes = []): Xlsform
{
    $template = XlsformTemplate::forceCreateQuietly(['title' => "{$title} template"]);

    return Xlsform::withoutEvents(fn () => Xlsform::create(array_merge([
        'xlsform_template_id' => $template->id,
        'owner_id' => $team->id,
        'title' => $title,
        'has_draft' => '1',
        'odk_id' => Str::slug($title),
        'odk_draft_token' => 'token',
        'draft_needs_update' => false,
    ], $attributes)));
}

function storedPilotXlsform(Xlsform $xlsform): Xlsform
{
    return Xlsform::withoutGlobalScopes()->findOrFail($xlsform->id);
}

beforeEach(function () {
    Http::fake();
    $this->team = Team::factory()->create();
    OdkProject::create(['id' => 1, 'owner_type' => Team::class, 'owner_id' => $this->team->id, 'name' => 'Project 1']);

    $this->user = createAppUser($this->team);
    $this->actingAs($this->user);
    withAppTenant($this->team);
});

test('opening the page does not queue anything or change any form', function () {
    Bus::fake();
    $xlsform = createPilotXlsform($this->team, 'Farm form', ['draft_needs_update' => true]);

    livewire(InitialPilot::class)->assertSuccessful();

    Bus::assertNothingDispatched();

    $stored = storedPilotXlsform($xlsform);
    expect($stored->processing)->toBeFalsy();
    expect($stored->draft_claimed_at)->toBeNull();
    expect($stored->draft_needs_update)->toBeTruthy();
});

test('the update button claims the outdated forms and queues one job for them', function () {
    Bus::fake();
    $outdated = createPilotXlsform($this->team, 'Outdated form', ['draft_needs_update' => true]);
    $current = createPilotXlsform($this->team, 'Current form');

    livewire(InitialPilot::class)
        ->callAction('update-test-forms')
        ->assertNotified('Updating the test forms');

    Bus::assertDispatchedTimes(DeployTeamDraftForms::class, 1);
    Bus::assertDispatched(DeployTeamDraftForms::class, fn (DeployTeamDraftForms $job) => $job->formIds === [$outdated->id] && $job->user->is($this->user));

    expect(storedPilotXlsform($outdated)->hasActiveClaim())->toBeTrue();
    expect(storedPilotXlsform($current)->processing)->toBeFalsy();
});

test('a form that was never deployed is updated too', function () {
    Bus::fake();
    $neverDeployed = createPilotXlsform($this->team, 'New form', ['has_draft' => '0', 'odk_draft_token' => null]);

    livewire(InitialPilot::class)->callAction('update-test-forms');

    Bus::assertDispatched(DeployTeamDraftForms::class, fn (DeployTeamDraftForms $job) => $job->formIds === [$neverDeployed->id]);
});

test('a form whose claim was abandoned is updated again', function () {
    Bus::fake();
    $stuck = createPilotXlsform($this->team, 'Stuck form', [
        'processing' => true,
        'draft_claimed_at' => now()->subHour(),
    ]);

    livewire(InitialPilot::class)->callAction('update-test-forms');

    Bus::assertDispatched(DeployTeamDraftForms::class, fn (DeployTeamDraftForms $job) => $job->formIds === [$stuck->id]);
    expect(storedPilotXlsform($stuck)->hasActiveClaim())->toBeTrue();
});

test('the update button is disabled while a form is updating and when everything is up to date', function () {
    Bus::fake();
    createPilotXlsform($this->team, 'Updating form', ['processing' => true, 'draft_claimed_at' => now()]);
    createPilotXlsform($this->team, 'Outdated form', ['draft_needs_update' => true]);

    livewire(InitialPilot::class)->assertActionDisabled('update-test-forms');

    Xlsform::withoutGlobalScopes()->update(['processing' => false, 'draft_claimed_at' => null, 'draft_needs_update' => false]);

    livewire(InitialPilot::class)->assertActionDisabled('update-test-forms');

    Bus::assertNothingDispatched();
});

test('a second update is refused while the first one holds the claims', function () {
    Bus::fake();
    createPilotXlsform($this->team, 'Outdated form', ['draft_needs_update' => true]);

    $page = livewire(InitialPilot::class)->callAction('update-test-forms');

    $page->assertActionDisabled('update-test-forms');

    Bus::assertDispatchedTimes(DeployTeamDraftForms::class, 1);
});

test('a failure while queueing the update releases the claims and keeps the forms outdated', function () {
    $xlsform = createPilotXlsform($this->team, 'Outdated form', ['draft_needs_update' => true]);

    Bus::shouldReceive('dispatch')->andThrow(new RuntimeException('queue is down'));

    livewire(InitialPilot::class)
        ->callAction('update-test-forms')
        ->assertNotified('The test forms could not be updated');

    $stored = storedPilotXlsform($xlsform);
    expect($stored->processing)->toBeFalsy();
    expect($stored->draft_claimed_at)->toBeNull();
    expect($stored->draft_needs_update)->toBeTruthy();
});

test('people who can only view the page do not get the button and are told who to ask', function () {
    $this->user->syncRoles([Role::where('name', 'Global Viewer')->first()]);
    createPilotXlsform($this->team, 'Outdated form', ['draft_needs_update' => true]);

    livewire(InitialPilot::class)
        ->assertActionHidden('update-test-forms')
        ->assertSee('Ask a team member who can update the test forms.');
});

test('only a form that is up to date shows its QR code and the time it includes changes up to', function () {
    createPilotXlsform($this->team, 'Current form', ['draft_snapshot_at' => '2026-11-18 10:15:30']);
    createPilotXlsform($this->team, 'Old form', ['draft_needs_update' => true, 'draft_snapshot_at' => '2026-11-17 08:00:00']);

    livewire(InitialPilot::class)
        ->assertSee('Includes all changes made up to 2026-11-18 10:15:30 UTC')
        ->assertSee('EXPIRED')
        ->assertSee('This test form includes changes made up to 2026-11-17 08:00:00 UTC. Changes made after that are not included.')
        ->assertSee('Some test forms are out of date.');

    expect(substr_count(livewire(InitialPilot::class)->html(), 'SCAN QR Code in ODK Collect'))->toBe(1);
});

test('an expired form never shows a QR code', function () {
    createPilotXlsform($this->team, 'Old form', ['draft_needs_update' => true]);
    createPilotXlsform($this->team, 'Unfinished form', ['processing' => true, 'draft_claimed_at' => now()->subHour()]);
    createPilotXlsform($this->team, 'New form', ['has_draft' => '0', 'odk_draft_token' => null]);

    livewire(InitialPilot::class)
        ->assertDontSee('SCAN QR Code in ODK Collect')
        ->assertSee('EXPIRED')
        ->assertSee('NO TEST FORM YET');
});

test('a form that is updating shows when it started and polls for changes', function () {
    createPilotXlsform($this->team, 'Updating form', ['processing' => true, 'draft_claimed_at' => '2026-11-18 10:15:30']);
    $this->travelTo('2026-11-18 10:16:00');

    livewire(InitialPilot::class)
        ->assertSee('Started at 2026-11-18 10:15:30 UTC')
        ->assertSee('If this does not finish within 15 minutes, you will be able to start it again.')
        ->assertSeeHtml('wire:poll.5s="refreshForms"')
        ->assertDontSee('SCAN QR Code in ODK Collect');
});

test('the page does not poll when nothing is updating', function () {
    createPilotXlsform($this->team, 'Current form');

    livewire(InitialPilot::class)->assertDontSeeHtml('wire:poll');
});

test('a team without forms sees an empty state', function () {
    livewire(InitialPilot::class)->assertSee('No test forms yet');
});

test('the page says a form is ready once an update the user was waiting for has finished', function () {
    $xlsform = createPilotXlsform($this->team, 'Updating form', ['processing' => true, 'draft_claimed_at' => now()]);

    $page = livewire(InitialPilot::class);

    Xlsform::withoutGlobalScopes()->whereKey($xlsform->id)->update([
        'processing' => false,
        'draft_claimed_at' => null,
        'draft_snapshot_at' => now(),
    ]);

    $page->call('refreshForms')->assertNotified('Test form updated');
});

test('the page stays quiet when an update ends and the form is still outdated', function () {
    $xlsform = createPilotXlsform($this->team, 'Updating form', ['processing' => true, 'draft_claimed_at' => now()]);

    $page = livewire(InitialPilot::class);

    Xlsform::withoutGlobalScopes()->whereKey($xlsform->id)->update([
        'processing' => false,
        'draft_claimed_at' => null,
        'draft_needs_update' => true,
    ]);

    $page->call('refreshForms')->assertNotNotified('Test form updated');
});
