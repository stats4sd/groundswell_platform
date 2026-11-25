<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Stats4sd\FilamentOdkLink\Exports\XlsformTemplateTranslationsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Tests\Support\ArrayUpload;
use Tests\Support\TranslationFixture;

function translationFileFromExport(
    TranslationFixture $fixture,
    XlsformTemplate $template,
    bool $withExistingStrings,
    callable $edit,
    string $fileName = 'template.xlsx',
): string {
    Excel::store(
        new XlsformTemplateTranslationsExport($template, $fixture->abkhazianLocale, withExistingStrings: $withExistingStrings, owner: $fixture->team),
        $fileName,
        'local',
    );

    $path = Storage::disk('local')->path($fileName);

    $spreadsheet = IOFactory::load($path);
    $sheet = $spreadsheet->getSheetByName('translations');

    $edit($sheet, $sheet->getHighestColumn());

    IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

    return $path;
}

function setTranslationCellByName(Worksheet $sheet, string $lastColumn, string $name, string $text): void
{
    foreach (range(2, (int) $sheet->getHighestRow()) as $rowNumber) {
        if ($sheet->getCell("D{$rowNumber}")->getValue() === $name) {
            $sheet->setCellValue("{$lastColumn}{$rowNumber}", $text);
        }
    }
}

function giveAbkhazianLabel(TranslationFixture $fixture, SurveyRow $surveyRow, string $text): void
{
    LanguageString::create([
        'locale_id' => $fixture->abkhazianLocale->id,
        'language_string_type_id' => LanguageStringType::where('name', 'label')->firstOrFail()->id,
        'linked_entry_id' => $surveyRow->id,
        'linked_entry_type' => SurveyRow::class,
        'text' => $text,
    ]);
}

function translationMediaCount(TranslationFixture $fixture): int
{
    return $fixture->abkhazianLocale->fresh()->getMedia('xlsform_template_translation_files')->count();
}

function addSecondTemplate(TranslationFixture $fixture): XlsformTemplate
{
    return Model::withoutEvents(function () use ($fixture): XlsformTemplate {
        $template = XlsformTemplate::forceCreate(['title' => 'Second Test Form']);
        $template->owner()->associate($fixture->team);
        $template->save();

        $module = XlsformModule::create([
            'xlsform_template_id' => $template->id,
            'label' => 'Second module',
            'name' => 'second',
        ]);

        $version = XlsformModuleVersion::create([
            'xlsform_module_id' => $module->id,
            'name' => 'v1',
            'is_default' => true,
        ]);

        Xlsform::create([
            'xlsform_template_id' => $template->id,
            'owner_id' => $fixture->team->id,
        ]);

        $surveyRow = SurveyRow::create([
            'name' => 'z1',
            'type' => 'text',
            'row_number' => 1,
            'xlsform_module_version_id' => $version->id,
        ]);

        LanguageString::create([
            'locale_id' => $fixture->englishLocale->id,
            'language_string_type_id' => LanguageStringType::where('name', 'label')->firstOrFail()->id,
            'linked_entry_id' => $surveyRow->id,
            'linked_entry_type' => SurveyRow::class,
            'text' => 'English label for z1',
        ]);

        $fixture->englishLocale->xlsformModuleVersions()->attach($version->id);
        $fixture->abkhazianLocale->xlsformModuleVersions()->attach($version->id);

        return $template->fresh();
    });
}

beforeEach(function () {
    Notification::fake();

    config()->set('filament-odk-link.storage.xlsforms', 'local');
    Storage::fake('local');
});

test('a pending upload stays in the form state and nothing is saved until the form is submitted', function () {
    $fixture = TranslationFixture::make();

    $path = translationFileFromExport($fixture, $fixture->template, false, fn () => null);

    $component = uploadTranslationFile(translationEditForm($fixture), $fixture->template, $path);

    $pendingUploads = $component->instance()->data["upload_for_template_{$fixture->template->id}"];

    expect($pendingUploads)->toHaveCount(1)
        ->and(collect($pendingUploads)->first())->toBeInstanceOf(TemporaryUploadedFile::class)
        ->and(translationMediaCount($fixture))->toBe(0);

    $component->assertSet('canSave', true);
});

test('saving imports only the file that was just uploaded, not the files attached earlier', function () {
    $fixture = TranslationFixture::make();
    $secondTemplate = addSecondTemplate($fixture);

    $oldPath = translationFileFromExport(
        $fixture,
        $secondTemplate,
        false,
        fn (Worksheet $sheet, string $lastColumn) => setTranslationCellByName($sheet, $lastColumn, 'z1', 'OLD text for z1'),
        'old-second.xlsx',
    );

    $fixture->abkhazianLocale
        ->addMedia($oldPath)
        ->preservingOriginal()
        ->withCustomProperties(['xlsform_template_id' => $secondTemplate->id])
        ->toMediaCollection('xlsform_template_translation_files');

    $newPath = translationFileFromExport(
        $fixture,
        $fixture->template,
        false,
        function (Worksheet $sheet, string $lastColumn) {
            foreach (range(2, (int) $sheet->getHighestRow()) as $rowNumber) {
                $sheet->setCellValue("{$lastColumn}{$rowNumber}", 'NEW '.$sheet->getCell("D{$rowNumber}")->getValue());
            }
        },
        'new-first.xlsx',
    );

    uploadTranslationFile(translationEditForm($fixture), $fixture->template, $newPath)
        ->call('submit');

    $importedTexts = LanguageString::where('locale_id', $fixture->abkhazianLocale->id)->pluck('text');

    expect($importedTexts)->toHaveCount(4)
        ->and($importedTexts->every(fn (string $text) => str_starts_with($text, 'NEW ')))->toBeTrue()
        ->and(translationMediaCount($fixture))->toBe(2);
});

test('blank cells over existing translations show a warning and nothing is saved until it is confirmed', function () {
    $fixture = TranslationFixture::make();

    $erasedRow = $fixture->surveyRows->first();
    giveAbkhazianLabel($fixture, $erasedRow, 'Existing AB text');
    giveAbkhazianLabel($fixture, $fixture->surveyRows->last(), 'Other AB text');

    $path = translationFileFromExport(
        $fixture,
        $fixture->template,
        true,
        fn (Worksheet $sheet, string $lastColumn) => setTranslationCellByName($sheet, $lastColumn, $erasedRow->name, ''),
    );

    $component = uploadTranslationFile(translationEditForm($fixture), $fixture->template, $path)->call('submit');

    $warnings = $component->instance()->overwriteWarnings;

    expect($warnings)->toHaveCount(1)
        ->and($warnings[$fixture->template->id]['count'])->toBe(1)
        ->and($warnings[$fixture->template->id]['title'])->toBe('Translation Test Form')
        ->and(translationMediaCount($fixture))->toBe(0)
        ->and(LanguageString::where('locale_id', $fixture->abkhazianLocale->id)->pluck('text')->all())
        ->toContain('Existing AB text');

    $component->call('confirmOverwrite');

    expect($component->instance()->overwriteWarnings)->toBe([])
        ->and(translationMediaCount($fixture))->toBe(1)
        ->and(LanguageString::where('locale_id', $fixture->abkhazianLocale->id)
            ->where('linked_entry_id', $erasedRow->id)
            ->where('linked_entry_type', SurveyRow::class)
            ->first()->text)->toBe('');
});

test('going back from the warning clears it and leaves the locale untouched', function () {
    $fixture = TranslationFixture::make();

    $erasedRow = $fixture->surveyRows->first();
    giveAbkhazianLabel($fixture, $erasedRow, 'Existing AB text');

    $path = translationFileFromExport(
        $fixture,
        $fixture->template,
        true,
        fn (Worksheet $sheet, string $lastColumn) => setTranslationCellByName($sheet, $lastColumn, $erasedRow->name, ''),
    );

    $component = uploadTranslationFile(translationEditForm($fixture), $fixture->template, $path)
        ->call('submit')
        ->call('cancelOverwrite');

    expect($component->instance()->overwriteWarnings)->toBe([])
        ->and(translationMediaCount($fixture))->toBe(0)
        ->and(LanguageString::where('locale_id', $fixture->abkhazianLocale->id)->pluck('text')->all())->toBe(['Existing AB text']);
});

test('blank cells over nothing, or over a stored blank, import without a warning', function () {
    $fixture = TranslationFixture::make();

    giveAbkhazianLabel($fixture, $fixture->surveyRows->first(), ' ');

    $path = translationFileFromExport($fixture, $fixture->template, true, fn () => null);

    $component = uploadTranslationFile(translationEditForm($fixture), $fixture->template, $path)->call('submit');

    expect($component->instance()->overwriteWarnings)->toBe([])
        ->and(translationMediaCount($fixture))->toBe(1);
});

test('a file added after the warning is checked again and a higher count asks for confirmation again', function () {
    $fixture = TranslationFixture::make();

    $firstRow = $fixture->surveyRows->first();
    $secondRow = $fixture->surveyRows->last();
    giveAbkhazianLabel($fixture, $firstRow, 'Existing one');
    giveAbkhazianLabel($fixture, $secondRow, 'Existing two');

    $oneBlank = translationFileFromExport(
        $fixture,
        $fixture->template,
        true,
        fn (Worksheet $sheet, string $lastColumn) => setTranslationCellByName($sheet, $lastColumn, $firstRow->name, ''),
        'one-blank.xlsx',
    );

    $twoBlanks = translationFileFromExport(
        $fixture,
        $fixture->template,
        true,
        function (Worksheet $sheet, string $lastColumn) use ($firstRow, $secondRow) {
            setTranslationCellByName($sheet, $lastColumn, $firstRow->name, '');
            setTranslationCellByName($sheet, $lastColumn, $secondRow->name, '');
        },
        'two-blanks.xlsx',
    );

    $component = uploadTranslationFile(translationEditForm($fixture), $fixture->template, $oneBlank)->call('submit');

    expect($component->instance()->overwriteWarnings[$fixture->template->id]['count'])->toBe(1);

    $component = uploadTranslationFile($component, $fixture->template, $twoBlanks)->call('confirmOverwrite');

    expect($component->instance()->overwriteWarnings[$fixture->template->id]['count'])->toBe(3)
        ->and(translationMediaCount($fixture))->toBe(0);
});

test('a rejected file is removed from the form and nothing is saved for it', function () {
    $fixture = TranslationFixture::make();

    Excel::store(new ArrayUpload([['wrong', 'headers', 'entirely']]), 'bad.xlsx', 'local');

    $component = uploadTranslationFile(translationEditForm($fixture), $fixture->template, Storage::disk('local')->path('bad.xlsx'))
        ->call('submit');

    expect($component->instance()->data["upload_for_template_{$fixture->template->id}"] ?? [])->toBe([])
        ->and(translationMediaCount($fixture))->toBe(0)
        ->and($component->instance()->overwriteWarnings)->toBe([]);
});
