<?php

use App\Livewire\SurveyLanguages\TeamTranslationReviewEditForm;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Stats4sd\FilamentOdkLink\Exports\XlsformTemplateTranslationsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Tests\Support\ArrayUpload;
use Tests\Support\TranslationFixture;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

function translationEditForm(TranslationFixture $fixture)
{
    $user = createAppUser($fixture->team);
    $user->givePermissionTo('maintain survey translations');

    actingAs($user);
    withAppTenant($fixture->team);

    return livewire(TeamTranslationReviewEditForm::class, [
        'locale' => $fixture->abkhazianLocale,
        'team' => $fixture->team,
        'canMaintain' => true,
    ]);
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
