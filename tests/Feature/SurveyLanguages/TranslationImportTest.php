<?php

use App\Imports\TranslationUploadInspector;
use App\Imports\XlsformTemplateLanguageImport;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Tests\Support\ArrayUpload;
use Tests\Support\TranslationFixture;

function storeTranslationUpload(array $rows): string
{
    Excel::store(new ArrayUpload($rows), 'translation-upload.xlsx', 'local');

    return Storage::disk('local')->path('translation-upload.xlsx');
}

function uploadHeadings(): array
{
    return ['row type', 'choice_list_id', 'entry_id', 'name', 'translation type', 'English (default)', 'French (default)', 'Spanish (default)', 'Abkhazian (test)'];
}

function uploadRowsFor(TranslationFixture $fixture, callable $textFor): array
{
    $rows = [uploadHeadings()];

    foreach ($fixture->surveyRows as $surveyRow) {
        $rows[] = ['survey', '', $surveyRow->id, $surveyRow->name, 'label', 'en ref', 'French text', 'es ref', $textFor($surveyRow->name)];
    }

    foreach ($fixture->choiceListEntries as $entry) {
        $rows[] = ['choices', $entry->choice_list_id, $entry->id, $entry->name, 'label', 'en ref', 'French text', 'es ref', $textFor($entry->name)];
    }

    return $rows;
}

test('translation text is imported from the target locale column, not column G', function () {
    Notification::fake();
    Storage::fake('local');

    $fixture = TranslationFixture::make();
    $user = createAppUser($fixture->team);

    $path = storeTranslationUpload(uploadRowsFor($fixture, fn (string $name) => "AB Test; {$name}"));

    $inspector = new TranslationUploadInspector($path);

    expect($inspector->textColumnIndex($fixture->abkhazianLocale))->toBe(8)
        ->and($inspector->validate($fixture->abkhazianLocale, $fixture->template))->toBe([]);

    Excel::import(
        new XlsformTemplateLanguageImport($fixture->abkhazianLocale, $fixture->template, $user, 8),
        $path,
    );

    $importedTexts = LanguageString::where('locale_id', $fixture->abkhazianLocale->id)->pluck('text');

    expect($importedTexts)->toHaveCount(4);

    $importedTexts->each(fn (string $text) => expect($text)->toStartWith('AB Test; '));
});

test('the inspector reports a missing target locale column', function () {
    Storage::fake('local');

    $fixture = TranslationFixture::make();

    $headingsWithoutTarget = array_slice(uploadHeadings(), 0, 8);
    $path = storeTranslationUpload([$headingsWithoutTarget]);

    $inspector = new TranslationUploadInspector($path);

    expect($inspector->textColumnIndex($fixture->abkhazianLocale))->toBeNull()
        ->and($inspector->validate($fixture->abkhazianLocale, $fixture->template))
        ->toHaveCount(1)
        ->and($inspector->validate($fixture->abkhazianLocale, $fixture->template)[0])
        ->toContain('Abkhazian (test)');
});

test('the inspector reports rows that do not belong to the template', function () {
    Storage::fake('local');

    $fixture = TranslationFixture::make();

    $rows = uploadRowsFor($fixture, fn (string $name) => "AB Test; {$name}");
    $rows[1][2] = 999999;

    $path = storeTranslationUpload($rows);

    $errors = (new TranslationUploadInspector($path))->validate($fixture->abkhazianLocale, $fixture->template);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('Translation Test Form');
});

test('the import rejects a file where a row after the first has an unknown entry id', function () {
    Notification::fake();
    Storage::fake('local');

    $fixture = TranslationFixture::make();
    $user = createAppUser($fixture->team);

    $rows = uploadRowsFor($fixture, fn (string $name) => "AB Test; {$name}");
    $rows[2][2] = 999999;

    $path = storeTranslationUpload($rows);

    expect(function () use ($fixture, $user, $path) {
        Excel::import(
            new XlsformTemplateLanguageImport($fixture->abkhazianLocale, $fixture->template, $user, 8),
            $path,
        );
    })->toThrow(ValidationException::class);
});
