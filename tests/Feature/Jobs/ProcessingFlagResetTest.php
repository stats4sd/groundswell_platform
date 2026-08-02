<?php

use App\Models\Team;
use Stats4sd\FilamentOdkLink\Exports\XlsformExport\XlsformWorkbookExport;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateWorkbookImport;
use Stats4sd\FilamentOdkLink\Jobs\FinishChoiceListEntryImport;
use Stats4sd\FilamentOdkLink\Jobs\FinishSurveyRowImport;
use Stats4sd\FilamentOdkLink\Jobs\FinishXlsformTemplateImport;
use Stats4sd\FilamentOdkLink\Jobs\ImportAllLanguageStrings;
use Stats4sd\FilamentOdkLink\Jobs\LinkModuleVersionToLocales;
use Stats4sd\FilamentOdkLink\Jobs\PrepareSurveyRowPaths;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\DeployDraftXlsformToOdkCentral;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\PublishXlsformOnOdkCentral;
use Stats4sd\FilamentOdkLink\Jobs\XlsformDeployment\UpdateXlsformFile;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

function createProcessingTemplate(): XlsformTemplate
{
    return XlsformTemplate::forceCreateQuietly([
        'title' => 'Stuck Template',
        'processing' => true,
    ]);
}

function createProcessingXlsform(): Xlsform
{
    $team = Team::withoutEvents(fn () => Team::factory()->create());

    $template = XlsformTemplate::forceCreateQuietly([
        'title' => 'Deployment Template',
    ]);

    return Xlsform::withoutEvents(fn () => Xlsform::create([
        'xlsform_template_id' => $template->id,
        'owner_id' => $team->id,
        'title' => 'Stuck Form',
        'processing' => true,
    ]));
}

test('every template import chain job resets the processing flag on failure', function () {
    createSuperAdmin();

    $jobFactories = [
        fn (XlsformTemplate $template) => new XlsformTemplateWorkbookImport($template, collect(['survey' => collect(), 'choices' => collect()])),
        fn (XlsformTemplate $template) => new PrepareSurveyRowPaths($template),
        fn (XlsformTemplate $template) => new FinishSurveyRowImport($template),
        fn (XlsformTemplate $template) => new FinishChoiceListEntryImport($template),
        fn (XlsformTemplate $template) => new LinkModuleVersionToLocales($template, collect()),
        fn (XlsformTemplate $template) => new ImportAllLanguageStrings('temp/file.xlsx', $template, collect()),
        fn (XlsformTemplate $template) => new FinishXlsformTemplateImport($template),
    ];

    foreach ($jobFactories as $jobFactory) {
        $template = createProcessingTemplate();

        $jobFactory($template)->failed(new Exception('boom'));

        expect($template->fresh()->processing)
            ->toBeFalsy('processing flag was not reset by '.$jobFactory($template)::class);
    }
});

test('a failed workbook export resets the xlsform processing flag', function () {
    createSuperAdmin();
    $xlsform = createProcessingXlsform();

    (new XlsformWorkbookExport($xlsform))->failed(new Exception('boom'));

    expect($xlsform->fresh()->processing)->toBeFalsy();
});

test('a failed xlsform file update resets the xlsform processing flag', function () {
    createSuperAdmin();
    $xlsform = createProcessingXlsform();

    (new UpdateXlsformFile($xlsform, 'temp/file.xlsx'))->failed(new Exception('boom'));

    expect($xlsform->fresh()->processing)->toBeFalsy();
});

test('a failed draft deployment resets the xlsform processing flag', function () {
    createSuperAdmin();
    $xlsform = createProcessingXlsform();

    (new DeployDraftXlsformToOdkCentral($xlsform, true, null))->failed(new Exception('boom'));

    expect($xlsform->fresh()->processing)->toBeFalsy();
});

test('a failed publish resets the xlsform processing flag', function () {
    createSuperAdmin();
    $xlsform = createProcessingXlsform();

    (new PublishXlsformOnOdkCentral($xlsform, null))->failed(new Exception('boom'));

    expect($xlsform->fresh()->processing)->toBeFalsy();
});

test('publishForm returns null without throwing when the form is already processing', function () {
    $xlsform = createProcessingXlsform();

    expect($xlsform->publishForm())->toBeNull();
});
