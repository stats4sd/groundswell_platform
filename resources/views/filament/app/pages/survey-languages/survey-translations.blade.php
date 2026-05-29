<?php

use App\Filament\App\Pages\SurveyDashboard;

$surveyDashboardUrl = SurveyDashboard::getUrl();
?>

<x-filament-panels::page>

@php $instructions = tfile('survey-translations'); @endphp

<x-instructions-sidebar>
        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>
    </x-instructions-sidebar>

{{--        instructions1='TK'--}}
{{--        instructionsmarkcomplete=' you have selected and (where needed) uploaded the completed translation for each language you intend to use for the survey. '--}}
{{--        videoUrl='https://www.youtube.com/embed/VIDEO_ID'--}}


    <div id="languages">
        <!-- Main Section -->
        <div class=" container mx-auto xl:px-12 ">
            <div class="surveyblocks p-12 lg:p-16  h-full">

                <div class="mb-8 dropdown_tables">
                    <div class="pb-4">
                        <h3 class="">{{ t("Survey Languages") }}</h3>
                        <p class="mb-8">
                   
                      
                    {{ t("Below are the languages you selected for your survey. For each one, click on \"Select translation\" to see available translations, and either select an appropriate option from the list or add the translation for the language.") }}
                </p>
 </div>
                    @foreach($languages as $language)
                        <livewire:survey-languages.team-translation-entry :language="$language" :key="$language->id" :team="$team"/>
                    @endforeach

                </div>
            </div>
        </div>
    </div>

</x-filament-panels::page>
