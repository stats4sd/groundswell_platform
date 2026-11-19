<?php

use App\Imports\TranslationUploadInspector;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Stats4sd\FilamentOdkLink\Exports\XlsformTemplateTranslationsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Tests\Support\ArrayUpload;
use Tests\Support\TranslationFixture;

/**
 * @param  array{owner_id?: ?int, name?: string, with_module?: bool}  $attributes
 * @return array{version: XlsformModuleVersion, surveyRows: Collection<int, SurveyRow>, choiceListEntries: Collection<int, ChoiceListEntry>}
 */
function addModuleVersion(TranslationFixture $fixture, array $attributes = []): array
{
    return Model::withoutEvents(function () use ($fixture, $attributes): array {
        Http::fake();

        $version = XlsformModuleVersion::create([
            'xlsform_module_id' => ($attributes['with_module'] ?? true) ? $fixture->moduleVersion->xlsform_module_id : null,
            'owner_id' => $attributes['owner_id'] ?? $fixture->team->id,
            'name' => $attributes['name'] ?? 'copy',
            'is_default' => false,
        ]);

        $labelType = LanguageStringType::where('name', 'label')->firstOrFail();

        $makeString = fn (Model $entry, string $text): LanguageString => LanguageString::create([
            'locale_id' => $fixture->englishLocale->id,
            'language_string_type_id' => $labelType->id,
            'linked_entry_id' => $entry->id,
            'linked_entry_type' => $entry::class,
            'text' => $text,
        ]);

        $surveyRows = collect(['q1', 'q2'])->map(function (string $name, int $index) use ($version, $makeString): SurveyRow {
            $row = SurveyRow::create([
                'xlsform_module_version_id' => $version->id,
                'name' => $name,
                'type' => 'text',
                'row_number' => $index + 1,
            ]);
            $makeString($row, "{$version->name} {$name}");

            return $row;
        });

        $choiceList = ChoiceList::create([
            'xlsform_module_version_id' => $version->id,
            'list_name' => 'yes_no',
        ]);

        $choiceListEntries = collect(['yes', 'no'])->map(function (string $name) use ($choiceList, $version, $makeString): ChoiceListEntry {
            $entry = ChoiceListEntry::create([
                'choice_list_id' => $choiceList->id,
                'name' => $name,
            ]);
            $makeString($entry, "{$version->name} {$name}");

            return $entry;
        });

        return compact('version', 'surveyRows', 'choiceListEntries');
    });
}

function selectVersionsInForm(TranslationFixture $fixture, XlsformModuleVersion ...$versions): void
{
    $form = Xlsform::withoutGlobalScopes()->where('owner_id', $fixture->team->id)->firstOrFail();

    foreach ($versions as $index => $version) {
        $form->xlsformModuleVersions()->attach($version->id, ['order' => $index + 1]);
    }
}

function exportedEntryIds(TranslationFixture $fixture, ?Team $owner, string $rowType): array
{
    $export = new XlsformTemplateTranslationsExport($fixture->template, $fixture->abkhazianLocale, withExistingStrings: true, owner: $owner);

    return $export->collection()
        ->filter(fn (Collection $row): bool => $row[0] === $rowType)
        ->map(fn (Collection $row): int => $row[2])
        ->unique()
        ->sort()
        ->values()
        ->all();
}

function idsOf(Collection $models): array
{
    return $models->pluck('id')->sort()->values()->all();
}

test('a team whose form uses a cloned version gets the clone rows and none of the global rows', function () {
    $fixture = TranslationFixture::make();
    $clone = addModuleVersion($fixture, ['name' => 'clone']);

    selectVersionsInForm($fixture, $clone['version']);

    expect(exportedEntryIds($fixture, $fixture->team, 'survey'))->toBe(idsOf($clone['surveyRows']))
        ->and(exportedEntryIds($fixture, $fixture->team, 'choices'))->toBe(idsOf($clone['choiceListEntries']));
});

test('a team that uses the global version gets the global rows only', function () {
    $fixture = TranslationFixture::make();
    addModuleVersion($fixture, ['name' => 'clone']);

    selectVersionsInForm($fixture, $fixture->moduleVersion);

    expect(exportedEntryIds($fixture, $fixture->team, 'survey'))->toBe(idsOf($fixture->surveyRows))
        ->and(exportedEntryIds($fixture, $fixture->team, 'choices'))->toBe(idsOf($fixture->choiceListEntries));
});

test('another team\'s cloned version and an unselected local version never appear', function () {
    $fixture = TranslationFixture::make();
    $otherTeam = Team::factory()->create();
    addModuleVersion($fixture, ['name' => 'other team clone', 'owner_id' => $otherTeam->id]);
    addModuleVersion($fixture, ['name' => 'Local unused']);

    selectVersionsInForm($fixture, $fixture->moduleVersion);

    expect(exportedEntryIds($fixture, $fixture->team, 'survey'))->toBe(idsOf($fixture->surveyRows))
        ->and(exportedEntryIds($fixture, $fixture->team, 'choices'))->toBe(idsOf($fixture->choiceListEntries));
});

test('a selected version that belongs to no module of the template stays out of the file', function () {
    $fixture = TranslationFixture::make();
    $localContext = addModuleVersion($fixture, ['name' => 'Local Context', 'with_module' => false]);

    selectVersionsInForm($fixture, $fixture->moduleVersion, $localContext['version']);

    expect(exportedEntryIds($fixture, $fixture->team, 'survey'))->toBe(idsOf($fixture->surveyRows))
        ->and(exportedEntryIds($fixture, $fixture->team, 'choices'))->toBe(idsOf($fixture->choiceListEntries));
});

test('without an owner, or with a form that selected nothing, only the default versions are listed', function () {
    $fixture = TranslationFixture::make();
    addModuleVersion($fixture, ['name' => 'clone']);

    expect(exportedEntryIds($fixture, null, 'survey'))->toBe(idsOf($fixture->surveyRows))
        ->and(exportedEntryIds($fixture, $fixture->team, 'survey'))->toBe(idsOf($fixture->surveyRows))
        ->and(exportedEntryIds($fixture, $fixture->team, 'choices'))->toBe(idsOf($fixture->choiceListEntries));
});

test('a team without a form for the template falls back to the default versions', function () {
    $fixture = TranslationFixture::make();
    $teamWithoutForm = Team::factory()->create();

    expect(exportedEntryIds($fixture, $teamWithoutForm, 'survey'))->toBe(idsOf($fixture->surveyRows));
});

test('choice entries the team removed from context are left out, like in the generated form', function () {
    $fixture = TranslationFixture::make();

    selectVersionsInForm($fixture, $fixture->moduleVersion);

    $removed = $fixture->choiceListEntries->firstWhere('name', 'yes');
    $fixture->team->choiceListEntriesRemovedFromContext()->attach($removed);

    expect(exportedEntryIds($fixture, $fixture->team, 'choices'))->toBe([$fixture->choiceListEntries->firstWhere('name', 'no')->id]);
});

test('an entry is also left out when only a same-named entry of another module list was removed', function () {
    $fixture = TranslationFixture::make();
    $clone = addModuleVersion($fixture, ['name' => 'clone']);

    selectVersionsInForm($fixture, $fixture->moduleVersion);

    $fixture->team->choiceListEntriesRemovedFromContext()->attach($clone['choiceListEntries']->firstWhere('name', 'yes'));

    expect(exportedEntryIds($fixture, $fixture->team, 'choices'))->toBe([$fixture->choiceListEntries->firstWhere('name', 'no')->id]);
});

test('a file made before the change, with the id of a version the form does not select, still passes the upload check', function () {
    Storage::fake('local');

    $fixture = TranslationFixture::make();
    $clone = addModuleVersion($fixture, ['name' => 'clone']);

    selectVersionsInForm($fixture, $fixture->moduleVersion);

    $oldStyleRows = [
        ['row type', 'choice_list_id', 'entry_id', 'name', 'translation type', 'English (default)', 'French (default)', 'Spanish (default)', 'Abkhazian (test)'],
        ['survey', '', $clone['surveyRows']->first()->id, 'q1', 'label', 'en ref', '', '', 'AB text'],
        ['survey', '', $fixture->surveyRows->first()->id, 'q1', 'label', 'en ref', '', '', 'AB text'],
    ];

    Excel::store(new ArrayUpload($oldStyleRows), 'old-style.xlsx', 'local');

    $errors = (new TranslationUploadInspector(Storage::disk('local')->path('old-style.xlsx')))
        ->validate($fixture->abkhazianLocale, $fixture->template);

    expect($errors)->toBe([]);
});

test('the export builds its rows once, so styles and data agree', function () {
    $fixture = TranslationFixture::make();
    $clone = addModuleVersion($fixture, ['name' => 'clone']);

    selectVersionsInForm($fixture, $clone['version']);

    $export = new XlsformTemplateTranslationsExport($fixture->template, $fixture->abkhazianLocale, withExistingStrings: true, owner: $fixture->team);

    expect($export->collection())->toBe($export->collection())
        ->and($export->collection())->toHaveCount(4);
});
