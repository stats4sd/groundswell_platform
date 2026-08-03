<?php

use App\Livewire\SurveyLanguages\TeamTranslationReviewEditForm;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Stats4sd\FilamentOdkLink\Exports\XlsformTemplateTranslationsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Tests\Support\ArrayUpload;
use Tests\Support\TranslationFixture;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

function translationEditForm(TranslationFixture $fixture, ?Locale $locale = null)
{
    $user = createAppUser($fixture->team);
    $user->givePermissionTo('maintain survey translations');

    actingAs($user);
    withAppTenant($fixture->team);

    return livewire(TeamTranslationReviewEditForm::class, [
        'locale' => $locale ?? $fixture->abkhazianLocale,
        'team' => $fixture->team,
        'canMaintain' => true,
    ]);
}

function attachTranslationUpload(TranslationFixture $fixture, Locale $locale): void
{
    Excel::store(new ArrayUpload([['some', 'translations']]), 'upload.xlsx', 'local');

    $locale
        ->addMedia(Storage::disk('local')->path('upload.xlsx'))
        ->preservingOriginal()
        ->withCustomProperties(['xlsform_template_id' => $fixture->template->id])
        ->toMediaCollection('xlsform_template_translation_files');
}

function duplicateOf(TranslationFixture $fixture, Locale $source): Locale
{
    return Locale::where('creator_id', $fixture->team->id)
        ->where('description', "{$source->language_label} - duplicated")
        ->firstOrFail();
}

test('the two download actions are distinct and the empty template excludes existing strings', function () {
    $fixture = TranslationFixture::make();

    Excel::fake();

    translationEditForm($fixture)->callAction(TestAction::make("download_empty_{$fixture->template->id}")->schemaComponent());

    Excel::assertDownloaded(
        'Translation Test Form translation template - Abkhazian (test).xlsx',
        fn (XlsformTemplateTranslationsExport $export) => $export->withExistingStrings === false
            && $export->owner !== null
            && $export->owner->is($fixture->team),
    );
});

test('the existing-translations download includes existing strings', function () {
    $fixture = TranslationFixture::make();

    Excel::fake();

    translationEditForm($fixture)->callAction(TestAction::make("download_existing_{$fixture->template->id}")->schemaComponent());

    Excel::assertDownloaded(
        'Translation Test Form translation - Abkhazian (test).xlsx',
        fn (XlsformTemplateTranslationsExport $export) => $export->withExistingStrings === true
            && $export->owner !== null
            && $export->owner->is($fixture->team),
    );
});

test('submitting an exported template filled in by a translator imports the correct column (issue #36 round trip)', function () {
    Notification::fake();

    config()->set('filament-odk-link.storage.xlsforms', 'local');
    Storage::fake('local');

    $fixture = TranslationFixture::make();

    Excel::store(
        new XlsformTemplateTranslationsExport($fixture->template, $fixture->abkhazianLocale, withExistingStrings: false, owner: $fixture->team),
        'template.xlsx',
        'local',
    );

    $path = Storage::disk('local')->path('template.xlsx');

    $spreadsheet = IOFactory::load($path);
    $sheet = $spreadsheet->getSheetByName('translations');
    $lastColumn = $sheet->getHighestColumn();

    foreach (range(2, (int) $sheet->getHighestRow()) as $rowNumber) {
        $entryName = $sheet->getCell("D{$rowNumber}")->getValue();
        $sheet->setCellValue("{$lastColumn}{$rowNumber}", "AB Test; {$entryName}");
    }

    IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

    $fixture->abkhazianLocale
        ->addMedia($path)
        ->preservingOriginal()
        ->withCustomProperties(['xlsform_template_id' => $fixture->template->id])
        ->toMediaCollection('xlsform_template_translation_files');

    translationEditForm($fixture)->call('submit');

    $importedTexts = LanguageString::where('locale_id', $fixture->abkhazianLocale->id)->pluck('text');

    expect($importedTexts)->toHaveCount(4);

    $importedTexts->each(function (string $text) {
        expect($text)->toStartWith('AB Test; ');
    });

    expect($importedTexts->contains(fn (string $text) => str_contains($text, 'French')))->toBeFalse();
});

test('duplicating a translation copies its strings and notifies the user', function () {
    $fixture = TranslationFixture::make();

    translationEditForm($fixture)
        ->call('duplicate');

    $duplicate = Locale::where('creator_id', $fixture->team->id)
        ->where('description', 'like', '%duplicated%')
        ->firstOrFail();

    expect($duplicate->is_default)->toBeFalse()
        ->and(LanguageString::where('locale_id', $duplicate->id)->pluck('text')->sort()->values()->all())
        ->toEqual(LanguageString::where('locale_id', $fixture->abkhazianLocale->id)->pluck('text')->sort()->values()->all());
});

test('duplicating a default translation carries over its ready-for-use status', function () {
    config()->set('filament-odk-link.storage.xlsforms', 'local');
    Storage::fake('local');

    $fixture = TranslationFixture::make();

    translationEditForm($fixture, $fixture->englishLocale)->call('duplicate');

    $duplicate = duplicateOf($fixture, $fixture->englishLocale);

    expect($fixture->englishLocale->status)->toBe('Ready for use')
        ->and($duplicate->status)->toBe('Ready for use')
        ->and($duplicate->is_default)->toBeFalse()
        ->and($duplicate->getMedia('xlsform_template_translation_files'))->toHaveCount(1)
        ->and($duplicate->languageStrings()->count())->toBe($fixture->englishLocale->languageStrings()->count());
});

test('duplicating an uploaded translation copies the uploaded files so the copy stays ready for use', function () {
    config()->set('filament-odk-link.storage.xlsforms', 'local');
    Storage::fake('local');

    $fixture = TranslationFixture::make();

    attachTranslationUpload($fixture, $fixture->abkhazianLocale);

    translationEditForm($fixture, $fixture->abkhazianLocale->fresh())->call('duplicate');

    $duplicate = duplicateOf($fixture, $fixture->abkhazianLocale);

    expect($fixture->abkhazianLocale->fresh()->status)->toBe('Ready for use')
        ->and($duplicate->status)->toBe('Ready for use')
        ->and($duplicate->getMedia('xlsform_template_translation_files')->first()->custom_properties)
        ->toBe(['xlsform_template_id' => $fixture->template->id]);
});

test('a duplicated translation inherits a needs-updating status from its source', function () {
    config()->set('filament-odk-link.storage.xlsforms', 'local');
    Storage::fake('local');

    $fixture = TranslationFixture::make();

    attachTranslationUpload($fixture, $fixture->abkhazianLocale);

    $fixture->abkhazianLocale->xlsformModuleVersions()
        ->updateExistingPivot($fixture->moduleVersion->id, ['needs_update' => true]);

    translationEditForm($fixture, $fixture->abkhazianLocale->fresh())->call('duplicate');

    expect($fixture->abkhazianLocale->fresh()->status)->toBe('Needs updating')
        ->and(duplicateOf($fixture, $fixture->abkhazianLocale)->status)->toBe('Needs updating');
});

test('duplicating a translation with nothing uploaded leaves the copy as not uploaded', function () {
    config()->set('filament-odk-link.storage.xlsforms', 'local');
    Storage::fake('local');

    $fixture = TranslationFixture::make();

    translationEditForm($fixture)->call('duplicate');

    expect($fixture->abkhazianLocale->status)->toBe('Not uploaded')
        ->and(duplicateOf($fixture, $fixture->abkhazianLocale)->status)->toBe('Not uploaded');
});

test('a duplicate of a locale mid-import does not inherit the processing state', function () {
    config()->set('filament-odk-link.storage.xlsforms', 'local');
    Storage::fake('local');

    $fixture = TranslationFixture::make();

    attachTranslationUpload($fixture, $fixture->abkhazianLocale);

    $fixture->abkhazianLocale->update(['processing_count' => 1]);

    translationEditForm($fixture, $fixture->abkhazianLocale->fresh())->call('duplicate');

    expect($fixture->abkhazianLocale->fresh()->status)->toBe('Processing')
        ->and(duplicateOf($fixture, $fixture->abkhazianLocale)->status)->toBe('Ready for use');
});

test('a translation file with wrong headers is rejected, removed, and nothing is imported', function () {
    Notification::fake();

    config()->set('filament-odk-link.storage.xlsforms', 'local');
    Storage::fake('local');

    $fixture = TranslationFixture::make();

    Excel::store(new ArrayUpload([['wrong', 'headers', 'entirely']]), 'bad-upload.xlsx', 'local');

    $fixture->abkhazianLocale
        ->addMedia(Storage::disk('local')->path('bad-upload.xlsx'))
        ->preservingOriginal()
        ->withCustomProperties(['xlsform_template_id' => $fixture->template->id])
        ->toMediaCollection('xlsform_template_translation_files');

    translationEditForm($fixture)->call('submit')->assertNotified();

    expect($fixture->abkhazianLocale->fresh()->getMedia('xlsform_template_translation_files'))->toHaveCount(0)
        ->and(LanguageString::where('locale_id', $fixture->abkhazianLocale->id)->count())->toBe(0);
});
