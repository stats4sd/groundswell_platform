<?php

use App\Filament\App\Pages\PlaceAdaptations\InitialPilot;
use App\Jobs\NotifyUserThatLanguageImportIsComplete;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\TranslationFixture;

/** @return Collection<int, Notification> */
function notificationsShownToTheUser(): Collection
{
    $component = new Notifications;
    $component->mount();

    return collect($component->notifications)->values();
}

test('the import complete notification links to Initial Pilot and says the test forms need updating', function () {
    Event::fake();
    $fixture = TranslationFixture::make();
    $user = createAppUser($fixture->team);

    (new NotifyUserThatLanguageImportIsComplete($fixture->abkhazianLocale, $fixture->template, $user))->handle();

    $expectedUrl = InitialPilot::getUrl(panel: 'app', tenant: $fixture->team);

    $notification = notificationsShownToTheUser()->first(fn (Notification $shown): bool => $shown->getTitle() === 'Translation Import Complete');

    expect($notification)->not->toBeNull();
    expect($notification->getBody())->toContain('click "Update test forms"');
    expect(collect($notification->getActions())->map(fn ($action) => $action->getUrl())->all())->toBe([$expectedUrl]);
});

test('the notification has no link when the team has no form for the template', function () {
    Event::fake();
    $fixture = TranslationFixture::make();
    $user = createAppUser($fixture->team);
    DB::table('xlsforms')->delete();

    (new NotifyUserThatLanguageImportIsComplete($fixture->abkhazianLocale, $fixture->template, $user))->handle();

    $notification = notificationsShownToTheUser()->first(fn (Notification $shown): bool => $shown->getTitle() === 'Translation Import Complete');

    expect($notification)->not->toBeNull();
    expect($notification->getBody())->not->toContain('Update test forms');
    expect($notification->getActions())->toBe([]);
});
