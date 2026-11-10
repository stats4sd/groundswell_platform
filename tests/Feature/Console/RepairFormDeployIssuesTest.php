<?php

use App\Models\Team;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
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

describe('R3: versions', function () {

    function repairTestModule(XlsformTemplate $template, string $name, int $defaultOrder, int $rowCount = 0): XlsformModule
    {
        $module = XlsformModule::forceCreate([
            'xlsform_template_id' => $template->id,
            'label' => $name,
            'name' => $name,
            'default_order' => $defaultOrder,
        ]);

        $version = $module->defaultXlsformVersion;

        for ($rowNumber = 1; $rowNumber <= $rowCount; $rowNumber++) {
            DB::table('survey_rows')->insert([
                'xlsform_module_version_id' => $version->id,
                'row_number' => $rowNumber,
                'name' => "{$name}_q{$rowNumber}",
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $module;
    }

    function repairTestForm(XlsformTemplate $template, Team $team, array $orderPerVersionId): Xlsform
    {
        $formId = DB::table('xlsforms')->insertGetId([
            'xlsform_template_id' => $template->id,
            'owner_id' => $team->id,
            'has_latest_template' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($orderPerVersionId as $versionId => $order) {
            DB::table('selected_xlsform_module_versions')->insert([
                'xlsform_id' => $formId,
                'xlsform_module_version_id' => $versionId,
                'order' => $order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return Xlsform::withoutGlobalScopes()->find($formId);
    }

    beforeEach(function () {
        $this->template = repairTestTemplate('Women\'s Form');
        $this->metadata = repairTestModule($this->template, 'metadata', 1, 2);
        $this->introduction = repairTestModule($this->template, 'introduction', 2, 3);
        $this->locations = repairTestModule($this->template, 'locations', 3, 2);
    });

    test('a dry run lists an emptied clone and changes nothing', function () {
        $clone = XlsformModuleVersion::forceCreate([
            'xlsform_module_id' => $this->introduction->id,
            'owner_id' => $this->team->id,
            'name' => 'introduction - Team',
        ]);
        $xlsform = repairTestForm($this->template, $this->team, [
            $this->metadata->defaultXlsformVersion->id => 1,
            $clone->id => 2,
            $this->locations->defaultXlsformVersion->id => 3,
        ]);

        $this->artisan('odk:repair-form-deploy-issues --only=versions')
            ->expectsOutputToContain("Version {$clone->id} (introduction - Team)")
            ->assertSuccessful();

        expect(XlsformModuleVersion::find($clone->id))->not->toBeNull()
            ->and($xlsform->xlsformModuleVersions()->pluck('xlsform_module_versions.id')->all())->toContain($clone->id)
            ->and((bool) $xlsform->refresh()->draft_needs_update)->toBeFalse();
    });

    test('--apply switches the forms from an emptied clone back to the global version at the same position and deletes the clone', function () {
        $clone = XlsformModuleVersion::forceCreate([
            'xlsform_module_id' => $this->introduction->id,
            'owner_id' => $this->team->id,
            'name' => 'introduction - Team',
        ]);
        $xlsform = repairTestForm($this->template, $this->team, [
            $this->metadata->defaultXlsformVersion->id => 1,
            $clone->id => 2,
            $this->locations->defaultXlsformVersion->id => 3,
        ]);

        $this->artisan('odk:repair-form-deploy-issues --only=versions --apply')->assertSuccessful();

        $selected = $xlsform->xlsformModuleVersions()->get();

        expect(XlsformModuleVersion::find($clone->id))->toBeNull()
            ->and($selected->pluck('name')->all())->toBe(['Global metadata', 'Global introduction', 'Global locations'])
            ->and($selected->pluck('pivot.order')->all())->toBe([1, 2, 3])
            ->and((bool) $xlsform->refresh()->draft_needs_update)->toBeTrue();
    });

    test('--apply removes the selections of the copy itself instead of relying on a foreign key', function () {
        $clone = XlsformModuleVersion::forceCreate([
            'xlsform_module_id' => $this->introduction->id,
            'owner_id' => $this->team->id,
            'name' => 'introduction - Team',
        ]);
        repairTestForm($this->template, $this->team, [$clone->id => 2]);

        $selectionsLeftWhenDeleted = null;
        XlsformModuleVersion::deleting(function (XlsformModuleVersion $version) use ($clone, &$selectionsLeftWhenDeleted) {
            if ($version->id === $clone->id) {
                $selectionsLeftWhenDeleted = DB::table('selected_xlsform_module_versions')->where('xlsform_module_version_id', $clone->id)->count();
            }
        });

        $this->artisan('odk:repair-form-deploy-issues --only=versions --apply')->assertSuccessful();

        expect($selectionsLeftWhenDeleted)->toBe(0)
            ->and(DB::table('selected_xlsform_module_versions')->where('xlsform_module_version_id', $clone->id)->count())->toBe(0);
    });

    test('--apply removes the choice lists of the copy together with the language strings of their entries', function () {
        $clone = XlsformModuleVersion::forceCreate([
            'xlsform_module_id' => $this->introduction->id,
            'owner_id' => $this->team->id,
            'name' => 'introduction - Team',
        ]);
        $copiedList = ChoiceList::forceCreate(['xlsform_module_version_id' => $clone->id, 'list_name' => 'copied_list']);
        $copiedEntry = repairTestEntry($copiedList, 'copied_entry', label: 'Copied label');
        repairTestForm($this->template, $this->team, [$clone->id => 2]);

        $this->artisan('odk:repair-form-deploy-issues --only=versions --apply')->assertSuccessful();

        expect(DB::table('choice_lists')->where('id', $copiedList->id)->exists())->toBeFalse()
            ->and(DB::table('choice_list_entries')->where('id', $copiedEntry->id)->exists())->toBeFalse()
            ->and(DB::table('language_strings')->where('linked_entry_type', ChoiceListEntry::class)->where('linked_entry_id', $copiedEntry->id)->exists())->toBeFalse();
    });

    test('a clone that still has rows is left alone', function () {
        $clone = XlsformModuleVersion::forceCreate([
            'xlsform_module_id' => $this->introduction->id,
            'owner_id' => $this->team->id,
            'name' => 'introduction - Team',
        ]);
        DB::table('survey_rows')->insert([
            'xlsform_module_version_id' => $clone->id,
            'row_number' => 1,
            'name' => 'kept',
            'type' => 'text',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        repairTestForm($this->template, $this->team, [$clone->id => 2]);

        $this->artisan('odk:repair-form-deploy-issues --only=versions --apply')
            ->expectsOutputToContain('No team-owned module version has lost its survey rows.')
            ->assertSuccessful();

        expect(XlsformModuleVersion::find($clone->id))->not->toBeNull();
    });

    test('--apply rebuilds an emptied Local locations version and marks the team\'s forms of that template', function () {
        $localLocations = XlsformModuleVersion::forceCreate([
            'xlsform_module_id' => $this->locations->id,
            'owner_id' => $this->team->id,
            'name' => 'Local locations',
        ]);
        $xlsform = repairTestForm($this->template, $this->team, [$this->metadata->defaultXlsformVersion->id => 1]);

        $this->artisan('odk:repair-form-deploy-issues --only=versions --apply')->assertSuccessful();

        expect($localLocations->surveyRows()->count())->toBeGreaterThan(0)
            ->and((bool) $xlsform->refresh()->draft_needs_update)->toBeTrue();
    });

    test('an emptied Local version of a deleted team is reported and skipped without failing the run', function () {
        $localLocations = XlsformModuleVersion::forceCreate([
            'xlsform_module_id' => $this->locations->id,
            'owner_id' => $this->team->id,
            'name' => 'Local locations',
        ]);
        // a complete form, so the "missing module" repair does not flag it
        $xlsform = repairTestForm($this->template, $this->team, [
            $this->metadata->defaultXlsformVersion->id => 1,
            $this->introduction->defaultXlsformVersion->id => 2,
            $this->locations->defaultXlsformVersion->id => 3,
        ]);
        $this->team->delete();

        foreach (['--only=versions', '--only=versions --apply'] as $options) {
            $this->artisan("odk:repair-form-deploy-issues {$options}")
                ->expectsOutputToContain("team {$this->team->id} was not found")
                ->assertSuccessful();
        }

        expect($localLocations->surveyRows()->count())->toBe(0)
            ->and((bool) $xlsform->refresh()->draft_needs_update)->toBeFalse();
    });

    test('an emptied Local version of a module no builder knows is reported and not changed', function () {
        $other = repairTestModule($this->template, 'other', 4, 2);
        $localOther = XlsformModuleVersion::forceCreate([
            'xlsform_module_id' => $other->id,
            'owner_id' => $this->team->id,
            'name' => 'Local other',
        ]);

        $this->artisan('odk:repair-form-deploy-issues --only=versions --apply')
            ->expectsOutputToContain('no builder knows module "other"')
            ->assertSuccessful();

        expect($localOther->surveyRows()->count())->toBe(0);
    });

    test('--apply attaches a module the form is missing at its position in the template', function () {
        $xlsform = repairTestForm($this->template, $this->team, [
            $this->metadata->defaultXlsformVersion->id => 1,
            $this->locations->defaultXlsformVersion->id => 2,
        ]);

        $this->artisan('odk:repair-form-deploy-issues --only=versions')
            ->expectsOutputToContain('is missing: introduction')
            ->assertSuccessful();

        expect($xlsform->xlsformModuleVersions()->count())->toBe(2);

        $this->artisan('odk:repair-form-deploy-issues --only=versions --apply')->assertSuccessful();

        $selected = $xlsform->xlsformModuleVersions()->get();

        expect($selected->pluck('name')->all())->toBe(['Global metadata', 'Global introduction', 'Global locations'])
            ->and((bool) $xlsform->refresh()->draft_needs_update)->toBeTrue();
    });

});

test('--only rejects an unknown value', function () {
    $this->artisan('odk:repair-form-deploy-issues --only=everything')->assertFailed();
});
