<?php

use App\Models\Team;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

function repairTestTemplate(string $title): XlsformTemplate
{
    return XlsformTemplate::withoutEvents(fn () => XlsformTemplate::forceCreate(['title' => $title]));
}

function repairTestList(XlsformTemplate $template, string $moduleName, string $listName): ChoiceList
{
    $module = XlsformModule::forceCreate([
        'xlsform_template_id' => $template->id,
        'label' => $moduleName,
        'name' => $moduleName,
    ]);

    $version = XlsformModuleVersion::forceCreate([
        'xlsform_module_id' => $module->id,
        'name' => 'v1',
        'is_default' => true,
    ]);

    return ChoiceList::forceCreate([
        'xlsform_module_version_id' => $version->id,
        'list_name' => $listName,
        'is_localisable' => true,
        'has_custom_handling' => false,
    ]);
}

function repairTestEntry(ChoiceList $list, string $name, ?Team $owner = null, ?string $label = null): ChoiceListEntry
{
    $entry = ChoiceListEntry::create([
        'choice_list_id' => $list->id,
        'name' => $name,
        'owner_id' => $owner?->id,
    ]);

    if ($label !== null) {
        $entry->languageStrings()->create([
            'locale_id' => test()->team->locales()->value('locales.id'),
            'language_string_type_id' => LanguageStringType::where('name', 'label')->firstOrFail()->id,
            'text' => $label,
        ]);
    }

    return $entry;
}

beforeEach(function () {
    Http::fake();
    $this->team = Team::factory()->create();
    $this->actingAs(createSuperAdmin());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

describe('R1: labels', function () {

    beforeEach(function () {
        $template = repairTestTemplate('Global Indicators');

        $cropsList = repairTestList($template, 'crops', 'crop_list');
        $intensificationList = repairTestList($template, 'crop_intensification', 'crop_list');

        $this->unlabelledGlobal = repairTestEntry($cropsList, '1');
        repairTestEntry($cropsList, '41', $this->team, 'Bean');

        repairTestEntry($intensificationList, '1', label: 'Rice');
        repairTestEntry($intensificationList, '41', $this->team, 'Bean');
    });

    test('a dry run reports the unlabelled entries and changes nothing', function () {
        $this->artisan('odk:repair-form-deploy-issues --only=labels')
            ->expectsOutputToContain('1 list(s) with unlabelled global entries')
            ->assertSuccessful();

        expect($this->unlabelledGlobal->refresh()->languageStrings)->toHaveCount(0);
    });

    test('--apply copies the labels from the labelled copy of the list', function () {
        $this->artisan('odk:repair-form-deploy-issues --only=labels --apply')->assertSuccessful();

        expect($this->unlabelledGlobal->refresh()->languageStrings)->toHaveCount(1);
        expect($this->unlabelledGlobal->languageStrings->first()->text)->toBe('Rice');
    });

});

describe('R2: module versions of another template', function () {

    beforeEach(function () {
        $this->firstTemplate = repairTestTemplate('Farm Registration');
        $this->secondTemplate = repairTestTemplate('Global Indicators');

        $this->foreignModule = XlsformModule::create(['xlsform_template_id' => $this->firstTemplate->id, 'label' => 'locations', 'name' => 'locations']);
        $this->ownLocations = XlsformModule::create(['xlsform_template_id' => $this->secondTemplate->id, 'label' => 'locations', 'name' => 'locations']);
        $this->ownMetadata = XlsformModule::create(['xlsform_template_id' => $this->secondTemplate->id, 'label' => 'metadata', 'name' => 'metadata']);

        $this->xlsform = Xlsform::withoutEvents(fn () => Xlsform::forceCreate([
            'xlsform_template_id' => $this->secondTemplate->id,
            'owner_id' => $this->team->id,
            'title' => 'Global Indicators',
            'draft_needs_update' => false,
        ]));

        $this->xlsform->xlsformModuleVersions()->attach($this->foreignModule->defaultXlsformVersion, ['order' => 2]);
        $this->xlsform->xlsformModuleVersions()->attach($this->ownMetadata->defaultXlsformVersion, ['order' => 1]);
    });

    test('a dry run reports the foreign version and changes nothing', function () {
        $this->artisan('odk:repair-form-deploy-issues --only=modules')
            ->expectsOutputToContain('1 form(s) would be repaired.')
            ->assertSuccessful();

        expect($this->xlsform->xlsformModuleVersions()->pluck('xlsform_module_versions.id')->all())
            ->toContain($this->foreignModule->defaultXlsformVersion->id);
        expect((bool) $this->xlsform->refresh()->draft_needs_update)->toBeFalse();
    });

    test('--apply swaps the foreign version for the form\'s own default, keeps its other versions and marks it for a draft update', function () {
        $this->artisan('odk:repair-form-deploy-issues --only=modules --apply')->assertSuccessful();

        $selectedIds = $this->xlsform->xlsformModuleVersions()->pluck('xlsform_module_versions.id')->all();

        expect($selectedIds)->not->toContain($this->foreignModule->defaultXlsformVersion->id)
            ->and($selectedIds)->toContain($this->ownLocations->defaultXlsformVersion->id)
            ->and($selectedIds)->toContain($this->ownMetadata->defaultXlsformVersion->id);

        expect((bool) $this->xlsform->refresh()->draft_needs_update)->toBeTrue();
    });

    test('a form that only selects its own template\'s versions is left alone', function () {
        $this->xlsform->xlsformModuleVersions()->detach($this->foreignModule->defaultXlsformVersion->id);
        $this->xlsform->xlsformModuleVersions()->attach($this->ownLocations->defaultXlsformVersion, ['order' => 2]);

        $this->artisan('odk:repair-form-deploy-issues --only=modules --apply')
            ->expectsOutputToContain('No form selects a module version of another template.')
            ->assertSuccessful();

        expect((bool) $this->xlsform->refresh()->draft_needs_update)->toBeFalse();
    });

});

test('--only rejects an unknown value', function () {
    $this->artisan('odk:repair-form-deploy-issues --only=everything')->assertFailed();
});
