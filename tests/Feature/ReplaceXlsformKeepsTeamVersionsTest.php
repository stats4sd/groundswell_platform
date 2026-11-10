<?php

use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Listeners\HandleXlsformTemplateAdded;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

function importIntoTemplate(XlsformTemplate $template, string $file): void
{
    $listener = new HandleXlsformTemplateAdded;
    $listener->createModules($file, $template);
    $listener->processXlsformTemplate($file, $template);
}

function defaultVersionOf(XlsformTemplate $template, string $moduleName): XlsformModuleVersion
{
    return $template->xlsformModules()->where('name', $moduleName)->firstOrFail()->defaultXlsformVersion;
}

function rowCount(XlsformModuleVersion $version): int
{
    return DB::table('survey_rows')->where('xlsform_module_version_id', $version->id)->count();
}

function surveyStringCount(XlsformModuleVersion $version): int
{
    return LanguageString::where('linked_entry_type', SurveyRow::class)
        ->whereIn('linked_entry_id', DB::table('survey_rows')->where('xlsform_module_version_id', $version->id)->pluck('id'))
        ->count();
}

beforeEach(function () {
    $this->team = Team::withoutEvents(fn () => Team::factory()->create());

    $this->template = XlsformTemplate::forceCreateQuietly(['title' => 'Replace Test Template']);
    $this->template->owner()->associate($this->team);
    $this->template->save();

    importIntoTemplate($this->template, 'tests/assets/replace-xlsform-v1.xlsx');

    $this->drinksClone = defaultVersionOf($this->template, 'drinks')->cloneForOwner($this->team);
    $this->coffeeClone = defaultVersionOf($this->template, 'coffee')->cloneForOwner($this->team);

    $coffeeCloneRow = $this->coffeeClone->surveyRows()->where('name', 'coffee')->firstOrFail();
    DB::table('language_strings')
        ->where('linked_entry_type', SurveyRow::class)
        ->where('linked_entry_id', $coffeeCloneRow->id)
        ->update(['text' => 'TEAM TEXT']);

    $contextModule = $this->template->xlsformModules()->where('name', 'context')->firstOrFail();
    $this->localVersion = XlsformModuleVersion::create([
        'xlsform_module_id' => $contextModule->id,
        'owner_id' => $this->team->id,
        'name' => 'Local context',
    ]);
    DB::table('survey_rows')->insert([
        'xlsform_module_version_id' => $this->localVersion->id,
        'row_number' => 1,
        'name' => 'local_question',
        'type' => 'text',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $globalList = defaultVersionOf($this->template, 'drinks')->choiceLists()->firstOrFail();
    $this->teamEntryId = DB::table('choice_list_entries')->insertGetId([
        'choice_list_id' => $globalList->id,
        'name' => 'team_entry',
        'owner_id' => $this->team->id,
        'properties' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherTemplate = XlsformTemplate::forceCreateQuietly(['title' => 'Another Template']);
    $otherModuleId = DB::table('xlsform_modules')->insertGetId([
        'xlsform_template_id' => $otherTemplate->id,
        'name' => 'other',
        'label' => 'other',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $otherVersionId = DB::table('xlsform_module_versions')->insertGetId([
        'xlsform_module_id' => $otherModuleId,
        'name' => 'Global other',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->otherEmptyListId = DB::table('choice_lists')->insertGetId([
        'xlsform_module_version_id' => $otherVersionId,
        'list_name' => 'other_empty_list',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->formId = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $this->template->id,
        'owner_id' => $this->team->id,
        'draft_needs_update' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->drinksCloneRowsBefore = rowCount($this->drinksClone);
    $this->drinksCloneStringsBefore = surveyStringCount($this->drinksClone);

    importIntoTemplate($this->template, 'tests/assets/replace-xlsform-v2.xlsx');
});

test('replacing the file updates the global rows exactly', function () {
    $names = $this->template->surveyRowsForImport()->pluck('survey_rows.name');

    expect($names)->toContain('new_q1', 'new_q2', 'drinks_rpt_grp2')
        ->and($names)->not->toContain('age')
        ->and($names)->not->toContain('drinks_rpt_grp');
});

test('replacing the file leaves a team clone of a module untouched', function () {
    expect(rowCount($this->drinksClone))->toBe($this->drinksCloneRowsBefore)
        ->and($this->drinksCloneRowsBefore)->toBeGreaterThan(0)
        ->and(surveyStringCount($this->drinksClone))->toBe($this->drinksCloneStringsBefore)
        ->and($this->drinksClone->surveyRows()->where('name', 'drinks_rpt_grp')->exists())->toBeTrue();
});

test('replacing the file leaves a team local version untouched', function () {
    expect(rowCount($this->localVersion))->toBe(1);
});

test('language strings go to the global row, not to a same-named row of a team clone', function () {
    $cloneRow = $this->coffeeClone->surveyRows()->where('name', 'coffee')->firstOrFail();
    $globalRow = defaultVersionOf($this->template, 'coffee')->surveyRows()->where('name', 'coffee')->firstOrFail();

    $cloneLabel = LanguageString::where('linked_entry_type', SurveyRow::class)->where('linked_entry_id', $cloneRow->id)->value('text');
    $globalLabel = LanguageString::where('linked_entry_type', SurveyRow::class)->where('linked_entry_id', $globalRow->id)->value('text');

    expect($cloneLabel)->toBe('TEAM TEXT')
        ->and($globalLabel)->toBe('Do you drink coffee?');
});

test('team-owned choice entries are kept, and empty lists of other templates are not deleted', function () {
    expect(DB::table('choice_list_entries')->where('id', $this->teamEntryId)->exists())->toBeTrue()
        ->and(DB::table('choice_lists')->where('id', $this->otherEmptyListId)->exists())->toBeTrue();
});

test('the paths of global rows are not changed by team clones', function () {
    $drinkName = defaultVersionOf($this->template, 'drinks')->surveyRows()->where('name', 'drink_name')->firstOrFail();

    expect($drinkName->path)->toBe('/drinks_rpt_grp2/drink_name')
        ->and($this->template->surveyRowsForImport()->where('survey_rows.path', 'like', '%drinks_rpt_grp2/drinks_rpt_grp2%')->exists())->toBeFalse();
});

test('a form of a deleted team does not stop the sync of the forms after it', function () {
    $deletedTeam = Team::withoutEvents(fn () => Team::factory()->create());
    $deletedTeamFormId = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $this->template->id,
        'owner_id' => $deletedTeam->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $laterFormId = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $this->template->id,
        'owner_id' => $this->team->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('xlsform_modules')->where('xlsform_template_id', $this->template->id)->update(['can_be_replaced' => true]);
    $deletedTeam->delete();

    importIntoTemplate($this->template, 'tests/assets/replace-xlsform-v2.xlsx');

    $moduleCount = $this->template->xlsformModules()->count();

    expect(DB::table('selected_xlsform_module_versions')->where('xlsform_id', $deletedTeamFormId)->count())->toBe($moduleCount)
        ->and(DB::table('selected_xlsform_module_versions')->where('xlsform_id', $laterFormId)->count())->toBe($moduleCount);
});

test('after the import the team forms select every module and are flagged for a new draft', function () {
    $form = DB::table('xlsforms')->where('id', $this->formId)->first();

    expect((bool) $form->draft_needs_update)->toBeTrue()
        ->and(DB::table('selected_xlsform_module_versions')->where('xlsform_id', $this->formId)->count())
        ->toBe($this->template->xlsformModules()->count());
});
