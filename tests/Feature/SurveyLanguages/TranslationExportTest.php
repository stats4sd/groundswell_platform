<?php

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
