<?php

use App\Filament\App\Pages\SurveyDashboard;

$surveyDashboardUrl = SurveyDashboard::getUrl();
?>

<x-filament-panels::page class="h-full">

@php $instructions = tfile('survey-languages'); @endphp

<x-instructions-sidebar>
        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
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
                <x-slot:heading>{{ t("Select Country and Languages") }}</x-slot:heading>
                <x-slot:description>{{ t("Pick the country you will be conducting the survey in, and the languages you will want to use. You can select multiple languages.") }}</x-slot:description>
                <x-slot:buttonLabel>{{ t("Update") }}</x-slot:buttonLabel>
            </x-rounded-section>

            <x-rounded-section
                :url="\App\Filament\App\Pages\SurveyLanguages\SurveyTranslations::getUrl()"
            >
                <x-slot:heading>{{ t("Survey Translations") }}</x-slot:heading>
                <x-slot:description>{{ t("Review the available translations for your survey. You can upload new translations if required.") }}</x-slot:description>
                <x-slot:buttonLabel>{{ t("Update") }}</x-slot:buttonLabel>
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
