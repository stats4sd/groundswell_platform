<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

function backfillTestTemplate(string $title, ?array $settingsRows, array $stored = []): XlsformTemplate
{
    $template = XlsformTemplate::withoutEvents(fn () => XlsformTemplate::forceCreate([
        'title' => $title,
        ...$stored,
    ]));

    if ($settingsRows === null) {
        return $template;
    }

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->setTitle('survey')->fromArray([['type', 'name']]);
    $spreadsheet->createSheet()->setTitle('settings')->fromArray($settingsRows);

    $path = tempnam(sys_get_temp_dir(), 'backfill-test-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    $template->addMedia($path)->toMediaCollection('xlsform_file');

    return $template->fresh();
}

beforeEach(function () {
    Storage::fake(config('filament-odk-link.storage.xlsforms'));
    Event::fake([MediaHasBeenAddedEvent::class]);
});

it('reports the changes without writing them in a dry run', function () {
    $template = backfillTestTemplate('Global Indicators', [
        ['default_language', 'instance_name'],
        ['English (en)', 'concat("GLOBAL:", ${interviewer_name})'],
    ]);

    $this->artisan('odk:backfill-template-settings')
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('concat("GLOBAL:", ${interviewer_name})')
        ->expectsOutputToContain('1 would be changed')
        ->assertSuccessful();

    expect($template->fresh()->instance_name)->toBeNull()
        ->and($template->fresh()->default_language)->toBeNull();
});

it('writes the settings from the file with --apply', function () {
    $template = backfillTestTemplate('Global Indicators', [
        ['default_language', 'instance_name'],
        ['English (en)', 'concat("GLOBAL:", ${interviewer_name})'],
    ]);

    $this->artisan('odk:backfill-template-settings --apply')
        ->expectsOutputToContain('APPLY')
        ->expectsOutputToContain('1 changed')
        ->assertSuccessful();

    expect($template->fresh()->instance_name)->toBe('concat("GLOBAL:", ${interviewer_name})')
        ->and($template->fresh()->default_language)->toBe('English (en)');
});

it('changes nothing when run a second time', function () {
    backfillTestTemplate('Global Indicators', [
        ['default_language', 'instance_name'],
        ['English (en)', 'concat("GLOBAL:", ${interviewer_name})'],
    ]);

    $this->artisan('odk:backfill-template-settings --apply')->assertSuccessful();

    $this->artisan('odk:backfill-template-settings --apply')
        ->expectsOutputToContain('0 changed, 1 unchanged')
        ->assertSuccessful();
});

it('skips a template that has no stored file', function () {
    $template = backfillTestTemplate('No File', null, ['instance_name' => 'concat("KEEP:", ${name})']);

    $this->artisan('odk:backfill-template-settings --apply')
        ->expectsOutputToContain('skipped: no stored xlsx file')
        ->expectsOutputToContain('1 skipped')
        ->assertSuccessful();

    expect($template->fresh()->instance_name)->toBe('concat("KEEP:", ${name})');
});

it('only writes the templates whose settings differ from the file', function () {
    $stored = ['instance_name' => 'concat("SAME:", ${name})', 'default_language' => 'English (en)'];
    backfillTestTemplate('Already Filled', [
        ['default_language', 'instance_name'],
        ['English (en)', 'concat("SAME:", ${name})'],
    ], $stored);
    backfillTestTemplate('Needs Filling', [
        ['default_language', 'instance_name'],
        ['English (en)', 'concat("NEW:", ${name})'],
    ]);

    $this->artisan('odk:backfill-template-settings --apply')
        ->expectsOutputToContain('2 template(s): 1 changed, 1 unchanged')
        ->assertSuccessful();
});

it('keeps going and fails at the end when a file cannot be read', function () {
    $broken = backfillTestTemplate('Broken File', [['instance_name'], ['x']]);
    $good = backfillTestTemplate('Good File', [
        ['instance_name'],
        ['concat("OK:", ${name})'],
    ]);
    file_put_contents($broken->getFirstMedia('xlsform_file')->getPath(), 'not an xlsx file');

    $this->artisan('odk:backfill-template-settings --apply')
        ->expectsOutputToContain('failed to read the file')
        ->expectsOutputToContain('1 failed')
        ->assertFailed();

    expect($good->fresh()->instance_name)->toBe('concat("OK:", ${name})');
});
