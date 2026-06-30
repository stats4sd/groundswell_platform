<?php

use App\Filament\App\Pages\PlaceAdaptations\HddsHints;
use App\Models\Team;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

use function Pest\Livewire\livewire;

/**
 * Build a team whose form includes an HDDS module version containing a single
 * survey row with an English label + hint. Everything is created with
 * withoutEvents() where the model boot hooks would otherwise reach out to ODK
 * Central or pre-flag the form for an update.
 */
function makeTeamWithHdds(): array
{
    $team = Team::withoutEvents(fn () => Team::factory()->create());

    // A real team always has an ODK project; without one the package's odk_qr_code
    // accessor (appended to Team) throws when the model is serialised by Livewire.
    $team->odkProject()->create([
        'id' => $team->id,
        'name' => 'Test Project',
    ]);

    // Give the team an English locale (normally done by the Team 'created' hook).
    $en = Language::where('iso_alpha2', 'en')->first();
    $locale = $en->defaultLocale ?? $en->locales()->create(['is_default' => true]);
    $team->languages()->syncWithoutDetaching([$en->id => ['locale_id' => $locale->id]]);

    $template = XlsformTemplate::withoutEvents(fn () => XlsformTemplate::create([
        'title' => 'Test Template',
        'available' => true,
    ]));

    // Creating the module fires its 'created' hook, which makes a default
    // "Global HDDS" XlsformModuleVersion linked to this module.
    $module = XlsformModule::create([
        'xlsform_template_id' => $template->id,
        'label' => 'HDDS',
        'name' => 'HDDS',
    ]);
    $moduleVersion = $module->xlsformModuleVersions()->first();

    $xlsform = Xlsform::withoutEvents(fn () => Xlsform::create([
        'xlsform_template_id' => $template->id,
        'owner_id' => $team->id,
        'title' => 'Test Form',
    ]));

    $moduleVersion->xlsforms()->attach($xlsform, ['order' => 1]);

    $surveyRow = SurveyRow::withoutEvents(fn () => $moduleVersion->surveyRows()->create([
        'name' => 'cereals',
        'type' => 'select_one cereals',
        'row_number' => 1,
    ]));

    $labelType = LanguageStringType::where('name', 'label')->first();
    $hintType = LanguageStringType::where('name', 'hint')->first();

    $surveyRow->languageStrings()->create([
        'locale_id' => $locale->id,
        'language_string_type_id' => $labelType->id,
        'text' => 'Cereals',
    ]);
    $surveyRow->languageStrings()->create([
        'locale_id' => $locale->id,
        'language_string_type_id' => $hintType->id,
        'text' => 'Original hint',
    ]);

    return compact('team', 'locale', 'xlsform', 'surveyRow');
}

describe('HDDS hints page', function () {

    beforeEach(function () {
        $scenario = makeTeamWithHdds();
        $this->team = $scenario['team'];
        $this->locale = $scenario['locale'];
        $this->xlsform = $scenario['xlsform'];
        $this->surveyRow = $scenario['surveyRow'];
        $this->user = createAppUser($this->team);
        $this->actingAs($this->user);
    });

    test('hddsModuleVersion resolves the team HDDS module version', function () {
        expect($this->team->hddsModuleVersion())->not->toBeNull();
    });

    test('page loads and lists HDDS questions with label and hint', function () {
        withAppTenant($this->team);

        $component = livewire(HddsHints::class)
            ->assertSuccessful()
            ->assertSee('cereals')
            ->assertSee('Original hint');

        // First visit clones the global HDDS version into a team-owned copy with
        // fresh SurveyRow ids; assert against the cloned row (matched by name).
        $clonedRow = $this->team->hddsModuleVersion()->surveyRows()->where('name', 'cereals')->first();

        $component->assertCanSeeTableRecords([$clonedRow]);
    });

    test('editing a hint updates the language string and flags the form for update', function () {
        withAppTenant($this->team);

        expect($this->xlsform->fresh()->draft_needs_update)->toBeFalsy();

        $component = livewire(HddsHints::class);

        // First visit clones the global HDDS version; the edit action operates on
        // the cloned SurveyRow, not the global original created in the fixture.
        $clonedRow = $this->team->hddsModuleVersion()->surveyRows()->where('name', 'cereals')->first();

        $component
            ->callTableAction('edit_hints', $clonedRow, data: [
                'hints' => [$this->locale->id => 'Updated hint text'],
            ])
            ->assertHasNoTableActionErrors();

        expect($clonedRow->getLanguageString('hint', $this->locale))->toBe('Updated hint text');
        expect($this->xlsform->fresh()->draft_needs_update)->toBeTruthy();
    });
});

test('page returns 404 for a team without an HDDS module version', function () {
    $team = Team::withoutEvents(fn () => Team::factory()->create());
    $user = createAppUser($team);

    $this->actingAs($user)
        ->get("/app/{$team->id}/hdds-hints")
        ->assertNotFound();
});
