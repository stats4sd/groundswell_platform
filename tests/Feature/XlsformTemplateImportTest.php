<?php

use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Listeners\HandleXlsformTemplateAdded;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;

test('an xlsform template is correctly imported and updated', function () {

    $this->xlsformTemplate = XlsformTemplate::forceCreateQuietly([
        'title' => 'Test Template',
    ]);


    // manually trigger the import
    $listener = new HandleXlsformTemplateAdded();
    $moduleVersions = $listener->createModules('tests/assets/odk-example-form-1.xlsx', $this->xlsformTemplate);
    $listener->processXlsformTemplate('tests/assets/odk-example-form-1.xlsx', $moduleVersions);


    // check that the survey rows were imported correctly
    $this->assertDatabaseCount('survey_rows', 25);
    $this->assertDatabaseCount('choice_lists', 4);
    $this->assertDatabaseCount('choice_list_entries', 14);
    $this->assertDatabaseCount('xlsform_module_versions', 5);
    $this->assertDatabaseCount('language_strings', 45);


    // check for a specific entry
    $choiceEntry = ChoiceListEntry::where('name', '=', 'juice')->first();
    $this->assertEquals('5', $choiceEntry->properties['filter2']);
});

//test('a language not in the xlsform template import is marked as needing an update', function () {
//
//
//    $this->xlsformTemplate = \Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate::forceCreateQuietly([
//        'title' => 'Test Template',
//    ]);
//
//    // add a new template language to the xlsform template
//    $this->xlsformTemplate->xlsformTemplateLanguages()->create([
//        'language_id' => \App\Models\XlsformLanguages\Language::where('iso_alpha2', 'es')->first()->id,
//    ]);
//
//    // ******** test that survey rows and language strings are deleted correctly ******* //
//    (new HandleXlsformTemplateAdded())->processXlsformTemplate('tests/assets/odk-example-form-3.xlsx', $this->xlsformTemplate);
//
//
//    $this->assertDatabaseCount('survey_rows', 3);
//    $this->assertDatabaseCount('xlsform_template_languages', 3);
//    $this->assertDatabaseCount('language_strings', 22);
//
//    // ******** test that the xlsform template languages are marked as needing an update updated correctly ******** //
//    // check that the template languages are not marked as needing an update
//    $this->assertDatabaseHas('xlsform_template_languages', [
//        'xlsform_template_id' => $this->xlsformTemplate->id,
//        'language_id' => \App\Models\XlsformLanguages\Language::where('iso_alpha2', 'en')->first()->id,
//        'needs_update' => 0,
//    ]);
//
//    $this->assertDatabaseHas('xlsform_template_languages', [
//        'xlsform_template_id' => $this->xlsformTemplate->id,
//        'language_id' => \App\Models\XlsformLanguages\Language::where('iso_alpha2', 'fr')->first()->id,
//        'needs_update' => 0,
//    ]);
//
//
//    $this->assertDatabaseHas('xlsform_template_languages', [
//        'xlsform_template_id' => $this->xlsformTemplate->id,
//        'language_id' => \App\Models\XlsformLanguages\Language::where('iso_alpha2', 'es')->first()->id,
//        'needs_update' => 1,
//    ]);
//
//});
