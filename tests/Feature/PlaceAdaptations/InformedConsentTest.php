<?php

use App\Filament\App\Pages\PlaceAdaptations\InformedConsent;
use App\Models\Team;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

use function Pest\Livewire\livewire;

/**
 * Build a team with two forms ("Global Indicators", "Women's Form"), each with
 * an introduction module version containing an enum_intro survey row with an
 * English label + hint. Mirrors makeTeamWithHdds() in HddsHintsTest.php.
 */
function makeTeamWithConsentForms(): array
{
    $team = Team::withoutEvents(fn () => Team::factory()->create());

    $team->odkProject()->create([
        'id' => $team->id,
        'name' => 'Test Project',
    ]);

    $en = Language::where('iso_alpha2', 'en')->first();
    $locale = $en->defaultLocale ?? $en->locales()->create(['is_default' => true]);
    $team->languages()->syncWithoutDetaching([$en->id => ['locale_id' => $locale->id]]);

    $labelType = LanguageStringType::where('name', 'label')->first();
    $hintType = LanguageStringType::where('name', 'hint')->first();

    $xlsforms = [];
    $surveyRows = [];

    foreach (['Global Indicators', "Women's Form"] as $index => $title) {
        $template = XlsformTemplate::withoutEvents(fn () => XlsformTemplate::create([
            'title' => "{$title} Template",
            'available' => true,
        ]));

        $module = XlsformModule::create([
            'xlsform_template_id' => $template->id,
            'label' => 'Introduction',
            'name' => 'introduction',
        ]);
        $moduleVersion = $module->xlsformModuleVersions()->first();

        $xlsform = Xlsform::withoutEvents(fn () => Xlsform::create([
            'xlsform_template_id' => $template->id,
            'owner_id' => $team->id,
            'title' => $title,
        ]));

        $moduleVersion->xlsforms()->attach($xlsform, ['order' => $index + 1]);

        $surveyRow = SurveyRow::withoutEvents(fn () => $moduleVersion->surveyRows()->create([
            'name' => 'enum_intro',
            'type' => 'note',
            'row_number' => 1,
        ]));

        $surveyRow->languageStrings()->create([
            'locale_id' => $locale->id,
            'language_string_type_id' => $labelType->id,
            'text' => "Enumerator: read out the following statement ({$title})",
        ]);
        $surveyRow->languageStrings()->create([
            'locale_id' => $locale->id,
            'language_string_type_id' => $hintType->id,
            'text' => "Consent line 1\nConsent **line** 2 ({$title})",
        ]);

        $xlsforms[] = $xlsform;
        $surveyRows[] = $surveyRow;
    }

    return compact('team', 'locale', 'xlsforms', 'surveyRows');
}

function teamConsentRow(Team $team, Xlsform $xlsform): SurveyRow
{
    return $xlsform->xlsformModuleVersions()
        ->where('owner_id', $team->id)
        ->first()
        ->surveyRows()
        ->where('name', 'enum_intro')
        ->first();
}

describe('Informed consent page', function () {

    beforeEach(function () {
        $scenario = makeTeamWithConsentForms();
        $this->team = $scenario['team'];
        $this->locale = $scenario['locale'];
        $this->xlsforms = $scenario['xlsforms'];
        $this->user = createAppUser($this->team);
        $this->actingAs($this->user);
    });

    test('informedConsentXlsforms resolves the forms with an enum_intro row', function () {
        expect($this->team->informedConsentXlsforms()->pluck('title')->all())
            ->toBe(['Global Indicators', "Women's Form"]);
    });

    test('page loads with a section per form showing the label', function () {
        withAppTenant($this->team);

        livewire(InformedConsent::class)
            ->assertSuccessful()
            ->assertSee('Global Indicators')
            ->assertSee("Women's Form")
            ->assertSee('Enumerator: read out the following statement (Global Indicators)');
    });

    test('editors are filled with the stored hint converted to html', function () {
        withAppTenant($this->team);

        $component = livewire(InformedConsent::class);

        $clonedRow = teamConsentRow($this->team, $this->xlsforms[0]);

        $formState = $component->instance()->form->getState();
        $editorHtml = $formState['consents'][$clonedRow->id][$this->locale->id];

        expect($editorHtml)->toContain('Consent line 1<br');
        expect($editorHtml)->toContain('<strong>line</strong> 2 (Global Indicators)');
    });

    test('first visit clones each module version for the team without flagging the forms', function () {
        withAppTenant($this->team);

        livewire(InformedConsent::class);

        foreach ($this->xlsforms as $xlsform) {
            $teamVersion = $xlsform->xlsformModuleVersions()->where('owner_id', $this->team->id)->first();

            expect($teamVersion)->not->toBeNull();
            expect($xlsform->xlsformModuleVersions()->whereNull('owner_id')->exists())->toBeFalse();
            expect($teamVersion->pivot->order)->toBeGreaterThan(0);
            expect($xlsform->fresh()->draft_needs_update)->toBeFalsy();
        }
    });

    test('saving writes odk markdown and flags the form for update', function () {
        withAppTenant($this->team);

        $component = livewire(InformedConsent::class);

        $clonedRow = teamConsentRow($this->team, $this->xlsforms[0]);

        $component
            ->fillForm([
                "consents.{$clonedRow->id}.{$this->locale->id}" => '<p>New <strong>bold</strong> text<br>second line</p>',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($clonedRow->getLanguageString('hint', $this->locale))->toBe("New **bold** text\nsecond line");
        expect($this->xlsforms[0]->fresh()->draft_needs_update)->toBeTruthy();
    });

    test('saving without edits leaves the text unchanged and does not flag the forms', function () {
        withAppTenant($this->team);

        livewire(InformedConsent::class)
            ->call('save')
            ->assertHasNoFormErrors();

        foreach ($this->xlsforms as $index => $xlsform) {
            $clonedRow = teamConsentRow($this->team, $xlsform);

            expect($clonedRow->getLanguageString('hint', $this->locale))
                ->toBe("Consent line 1\nConsent **line** 2 ({$xlsform->title})");
            expect($xlsform->fresh()->draft_needs_update)->toBeFalsy();
        }
    });

    test('a third form appears once its template gains an enum_intro row', function () {
        withAppTenant($this->team);

        $template = XlsformTemplate::withoutEvents(fn () => XlsformTemplate::create([
            'title' => 'Farm Registration Template',
            'available' => true,
        ]));

        $module = XlsformModule::create([
            'xlsform_template_id' => $template->id,
            'label' => 'Metadata',
            'name' => 'metadata',
        ]);
        $moduleVersion = $module->xlsformModuleVersions()->first();

        $xlsform = Xlsform::withoutEvents(fn () => Xlsform::create([
            'xlsform_template_id' => $template->id,
            'owner_id' => $this->team->id,
            'title' => 'Farm Registration',
        ]));

        $moduleVersion->xlsforms()->attach($xlsform, ['order' => 1]);

        livewire(InformedConsent::class)
            ->assertSuccessful()
            ->assertDontSee('Farm Registration');

        SurveyRow::withoutEvents(fn () => $moduleVersion->surveyRows()->create([
            'name' => 'enum_intro',
            'type' => 'note',
            'row_number' => 1,
        ]));

        livewire(InformedConsent::class)
            ->assertSuccessful()
            ->assertSee('Farm Registration');
    });
});

test('page returns 404 for a team without any enum_intro rows', function () {
    $team = Team::withoutEvents(fn () => Team::factory()->create());
    $user = createAppUser($team);

    $this->actingAs($user)
        ->get("/app/{$team->id}/informed-consent")
        ->assertNotFound();
});
