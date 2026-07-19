<?php

use App\Livewire\SurveyLanguages\TeamTranslationEntry;
use App\Models\Team;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Tests\Support\TranslationFixture;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

test('teams only see default locales and locales they created themselves', function () {
    $fixture = TranslationFixture::make();

    $otherTeam = Team::withoutEvents(fn () => Team::factory()->create());

    $abkhazianLanguageId = $fixture->abkhazianLocale->language_id;

    $defaultAbkhazianLocale = Locale::withoutEvents(fn () => Locale::create([
        'language_id' => $abkhazianLanguageId,
        'is_default' => true,
    ]));

    $otherTeamsLocale = Locale::withoutEvents(fn () => Locale::create([
        'language_id' => $abkhazianLanguageId,
        'is_default' => false,
        'creator_id' => $otherTeam->id,
        'description' => 'Abkhazian (other team)',
    ]));

    $user = createAppUser($fixture->team);
    actingAs($user);
    withAppTenant($fixture->team);

    $language = $fixture->team->languages->firstWhere('iso_alpha2', 'ab');

    livewire(TeamTranslationEntry::class, [
        'language' => $language,
        'team' => $fixture->team,
        'expanded' => true,
    ])
        ->assertCanSeeTableRecords([$fixture->abkhazianLocale, $defaultAbkhazianLocale])
        ->assertCanNotSeeTableRecords([$otherTeamsLocale]);
});
