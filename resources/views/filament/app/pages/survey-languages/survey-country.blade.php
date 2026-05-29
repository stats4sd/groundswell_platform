<?php

$instructions = tfile('survey-country');

?>

<x-filament-panels::page>

    <x-instructions-sidebar :videoUrl="'#'">
        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>
    </x-instructions-sidebar>
    <div class="container mx-auto  ">
        <div class="surveyblocks pt-16 pb-24 mb-32 px-12 lg:px-16">
            <h3>{{ t("Instructions") }}</h3>
            <p class="mb-8">{{ t("Please select the country and languages for your survey below.") }}</p>

            {{ $this->form }}

            @can('maintain select country and languages')
                <a href="{{ \App\Filament\App\Pages\SurveyLanguages\SurveyLanguagesIndex::getUrl() }}" class="buttona block max-w-sm mx-auto md:inline-block mb-6 md:mb-0 mt-12">{{ t("Save and Return") }}</a>
            @else
                <a href="{{ \App\Filament\App\Pages\SurveyLanguages\SurveyLanguagesIndex::getUrl() }}" class="buttona block max-w-sm mx-auto md:inline-block mb-6 md:mb-0 mt-12">{{ t("Return") }}</a>
            @endcan
            
        </div>
    </div>
</x-filament-panels::page>
