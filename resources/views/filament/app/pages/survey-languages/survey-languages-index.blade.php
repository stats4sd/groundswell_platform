<?php

use App\Filament\App\Pages\SurveyDashboard;

$surveyDashboardUrl = SurveyDashboard::getUrl();
?>

<x-filament-panels::page class="h-full">

<x-instructions-sidebar>
        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>

            {{-- <div class="pr-4 content-center  mx-auto my-4">
                <iframe class="rounded-3xl" src="https://www.youtube.com/embed/TODO_ADD_VIDEO_ID" style="width: 560px; height: 315px;" frameborder="0" allowfullscreen></iframe>
            </div> --}}
            <div class="mx-12 mb-4">
                <p class="mb-2">
                  The first step to set up the survey is to select the country and languages your team will conduct the survey in. Bear in mind that if you change these details later, you may need to review some of the other steps.
                </p>
                <h5>Select country and languages </h5>
                <p class="mb-2">
                    Start by selecting the country for the survey. You can click in the box and either scroll through the dropdown list or start typing to narrow down the options and find your country. If the country you need is not listed, you can use the "plus" button to add it. This will require you to input the country name and some additional details.
                </p>
                <p class="mb-2">
                    You can then start adding the languages in which you will conduct your survey. If you are going to run your survey in multiple languages, all of them need to be added here. Start typing in the box to find and add languages.
                </p>

                <h5>Survey translations </h5>
                <p class="mb-2">
                    When you have selected the languages for your survey, you should review the available translations.
                    </p>
                    <p class="mb-2">
                        HOLPA is available in multiple languages. On the translations page, for each of the languages you selected previously, you will need to select the translation to use. Click "Select translation" to show the available translations for a language.
                    </p>
                    <p class="mb-2">
                        If there is no existing translation, or the available translations are not suitable for your survey location, you should add a new translation. You may wish to duplicate an existing translation to use as a starting point.
                    </p>
                    <p class="mb-2">
                        To add a new translation, click on "add new" and give it a clear name. The translation will appear in the table. You can then use the "view/edit" menu to download the translation template. Add the translated text in the indicated column in the xls files, then reupload them using the same menu. Once both forms have translated versions uploaded, the translation will be available for use. When editing or adding a translation, make sure you download the file for the language you wish to edit, and make the changes in that file. If the column headers do not match the original file or if the column for the translated text is left blank, you will see an error message when you upload the files.
                    </p>
                    <p class="mb-2">
                        If you click on view/edit translation, this will allow you to download the xls files with the translated text.
                        You can edit translations that your team has added. To do this, download the existing translation files and follow the same process as when adding a translation: make changes in the column for the destination language and upload the edited file through the same menu. Note that the translation is to reflect the text <span class="font-semibold">as written in the HOLPA survey</span>; this is not the appropriate place to customise the content of the survey. Translations added by other teams or from the original ODK forms cannot be directly edited, but you can duplicate the translation to make a new version with your changes.
                    </p>
                    <p class="mb-2">
                        The HOLPA platform will make user-uploaded translations available to all other teams.
                    </p>
                    <p class="instructions_note">
                    <span class="font-semibold"> NOTE: </span> The platform assumes that you are working within one country. If you are conducting HOLPA across multiple countries, you will need to create separate teams within this platform for each country. Please contact the HOLPA support team if you require assistance with this.
                    </p>
                    <h5>Mark this section as complete when:</h5>

                    <p class="mb-2">
                        You have selected the country, languages and added a global HOLPA survey translation for each language.
                </p>

            </div>
        </x-slot:instructions>
    </x-instructions-sidebar>
{{--        instructions1='Your team will be running HOLPA within one country. The first step to set up the survey is to select the country of the survey and languages your team will conduct the survey in.'--}}
{{--        instructions2='When you have selected the languages for your survey, you should review the available translations. HOLPA is available in multiple languages. The Survey Translation page will show you if there are translations available in your chosen languages. There, you can review the available translations, and upload new translations if required.'--}}
{{--        instructions3="NOTE: if you are conducting HOLPA across multiple countries, you will need to create separate teams within this platform for each country. Please contact the HOLPA support team if you require assistance with this."--}}
{{--        instructionsmarkcomplete="you have selected the country, languages and added a global HOLPA survey translation for each language."--}}
{{--        videoUrl='https://www.youtube.com/embed/VIDEO_ID'--}}


    <div class="container mx-auto xl:px-12 ">
        <div class="surveyblocks pr-10 pt-8">

            <x-rounded-section
                :url="\App\Filament\App\Pages\SurveyLanguages\SurveyCountry::getUrl()"
            >
                <x-slot:heading>Select Country and Languages</x-slot:heading>
                <x-slot:description>Pick the country you will be conducting the survey in, and the languages you will want to use. You can select multiple languages.</x-slot:description>
                <x-slot:buttonLabel>Update</x-slot:buttonLabel>
            </x-rounded-section>

            <x-rounded-section
                :url="\App\Filament\App\Pages\SurveyLanguages\SurveyTranslations::getUrl()"
            >
                <x-slot:heading>Survey Translations</x-slot:heading>
                <x-slot:description>Review the available translations for your survey. You can upload new translations if required.</x-slot:description>
                <x-slot:buttonLabel>Update</x-slot:buttonLabel>
            </x-rounded-section>
        </div>
    </div>

    <!-- Footer -->
    <x-complete-section-status-bar :completion-prop="$completionProp">
        <x-slot:markCompleteAction>
            {{ $this->markCompleteAction() }}
        </x-slot:markCompleteAction>
        <x-slot:markIncompleteAction>
            {{ $this->markIncompleteAction() }}
        </x-slot:markIncompleteAction>
    </x-complete-section-status-bar>
</x-filament-panels::page>
