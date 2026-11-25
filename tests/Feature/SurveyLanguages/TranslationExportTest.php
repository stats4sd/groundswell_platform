<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use Stats4sd\FilamentOdkLink\Exports\XlsformTemplateTranslationsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Tests\Support\TranslationFixture;

test('reference columns are limited to the default languages selected by the owner team', function () {
    $fixture = TranslationFixture::make();

    $export = new XlsformTemplateTranslationsExport($fixture->template, $fixture->abkhazianLocale, withExistingStrings: true, owner: $fixture->team);

    expect($export->headings())->toBe([
        'row type', 'choice_list_id', 'entry_id', 'name', 'translation type',
        'English (default)', 'French (default)', 'Abkhazian (test)',
    ]);
});

test('all default template languages are included when no owner is given', function () {
    $fixture = TranslationFixture::make();

    $export = new XlsformTemplateTranslationsExport($fixture->template, $fixture->abkhazianLocale, withExistingStrings: true);

    expect($export->headings())->toBe([
        'row type', 'choice_list_id', 'entry_id', 'name', 'translation type',
        'English (default)', 'French (default)', 'Spanish (default)', 'Abkhazian (test)',
    ]);
});

test('the empty template leaves the translation column blank even when strings exist', function () {
    $fixture = TranslationFixture::make();

    LanguageString::create([
        'locale_id' => $fixture->abkhazianLocale->id,
        'language_string_type_id' => LanguageStringType::where('name', 'label')->firstOrFail()->id,
        'linked_entry_id' => $fixture->surveyRows->first()->id,
        'linked_entry_type' => SurveyRow::class,
        'text' => 'Existing AB text',
    ]);

    $emptyExport = new XlsformTemplateTranslationsExport($fixture->template, $fixture->abkhazianLocale, withExistingStrings: false, owner: $fixture->team);

    $emptyExport->collection()->each(fn (Collection $row) => expect($row->last())->toBe(''));

    $filledExport = new XlsformTemplateTranslationsExport($fixture->template, $fixture->abkhazianLocale, withExistingStrings: true, owner: $fixture->team);

    expect($filledExport->collection()->map(fn (Collection $row) => $row->last()))
        ->toContain('Existing AB text');
});

test('the exported file locks the identifier columns and unlocks the translation columns', function () {
    Storage::fake('local');

    $fixture = TranslationFixture::make();

    Excel::store(
        new XlsformTemplateTranslationsExport($fixture->template, $fixture->abkhazianLocale, withExistingStrings: false, owner: $fixture->team),
        'export.xlsx',
        'local',
    );

    $sheet = IOFactory::load(Storage::disk('local')->path('export.xlsx'))->getSheetByName('translations');

    expect($sheet->getProtection()->getSheet())->toBeTrue()
        ->and($sheet->getStyle('A2')->getProtection()->getLocked())->not->toBe(Protection::PROTECTION_UNPROTECTED)
        ->and($sheet->getStyle('C2')->getProtection()->getLocked())->not->toBe(Protection::PROTECTION_UNPROTECTED)
        ->and($sheet->getStyle('A1')->getProtection()->getLocked())->not->toBe(Protection::PROTECTION_UNPROTECTED)
        ->and($sheet->getStyle('H2')->getProtection()->getLocked())->toBe(Protection::PROTECTION_UNPROTECTED);
});

function addStructureRows(TranslationFixture $fixture): Collection
{
    return Model::withoutEvents(fn () => collect([
        'end group', 'end_group', ' End Group ', 'end repeat', 'end_repeat', 'end', 'begin group', 'begin_repeat',
    ])->map(function (string $type, int $index) use ($fixture): SurveyRow {
        $surveyRow = SurveyRow::create([
            'name' => 'structure_'.$index,
            'type' => $type,
            'row_number' => 10 + $index,
            'xlsform_module_version_id' => $fixture->moduleVersion->id,
        ]);

        LanguageString::create([
            'locale_id' => $fixture->englishLocale->id,
            'language_string_type_id' => LanguageStringType::where('name', 'label')->firstOrFail()->id,
            'linked_entry_id' => $surveyRow->id,
            'linked_entry_type' => SurveyRow::class,
            'text' => "English label for {$surveyRow->name}",
        ]);

        return $surveyRow;
    }));
}

test('the group end scope and check match end group and end repeat in any spelling and nothing else', function () {
    $fixture = TranslationFixture::make();

    $structureRows = addStructureRows($fixture);

    $groupEndTypes = SurveyRow::groupEnds()->pluck('type')->map(fn (string $type) => trim($type))->sort()->values()->all();

    expect($groupEndTypes)->toBe(['End Group', 'end group', 'end repeat', 'end_group', 'end_repeat'])
        ->and($structureRows->filter->isGroupEnd()->pluck('type')->map(fn (string $type) => trim($type))->sort()->values()->all())
        ->toBe(['End Group', 'end group', 'end repeat', 'end_group', 'end_repeat'])
        ->and(SurveyRow::where('type', 'end')->first()->isGroupEnd())->toBeFalse()
        ->and(SurveyRow::where('type', 'begin group')->first()->isGroupEnd())->toBeFalse();
});

test('the export leaves out end group and end repeat rows but keeps the end metadata row and begin rows', function () {
    $fixture = TranslationFixture::make();

    addStructureRows($fixture);

    $export = new XlsformTemplateTranslationsExport($fixture->template, $fixture->abkhazianLocale, withExistingStrings: true, owner: $fixture->team);

    $names = $export->collection()->map(fn (Collection $row) => $row[3])->unique()->values()->all();

    expect($names)->toContain('q1', 'q2', 'structure_5', 'structure_6', 'structure_7')
        ->and($names)->not->toContain('structure_0', 'structure_1', 'structure_2', 'structure_3', 'structure_4');
});
