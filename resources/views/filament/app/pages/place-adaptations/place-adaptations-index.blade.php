<?php

use App\Filament\App\Pages\SurveyDashboard;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;

$surveyDashboardUrl = SurveyDashboard::getUrl();
?>

<x-filament-panels::page class="px-10 h-full">

    @php $instructions = tfile('place-adaptations'); @endphp

    <x-instructions-sidebar>
        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>
    </x-instructions-sidebar>


    {{--        instructions1='The localisation sections allow you to adjust the HOLPA survey to ensure it is relevant to the target audience. The HOLPA tool aims to balance harmonisation and comparability between results with specific adaptations to ensure those results are applicable and useful at a local level. '--}}
    {{--        instructions2='In this first section, you can customise certain questions and answer options. For example, in different geographical locations, farmers would be growing different crops and different staple foods would be commonly consumed; the options in the questionnaire should reflect this. '--}}
    {{--        instructions3='Following customisation, a pilot test should be conducted to check the sense and functionality of the survey. The initial pilot page contains more detailed guidance on this process.'--}}
    {{--        instructionsmarkcomplete='you have made all the desired adaptions to the details available for change in this step, piloted the full survey with a local researcher or practitioner, and made any needed adjustments. '--}}
    {{--        videoUrl='https://www.youtube.com/embed/VIDEO_ID'--}}

    <div class="container mx-auto xl:px-12 ">
        <div class="surveyblocks pr-10  ">
            <div class="mb-12 -mr-10  px-16 pb-12 text-white bg-green">
                <p class="font-bold text-green text-lg pb-4">{{ t('CUSTOM SURVEY') }}</p>
                <p>
                    <b>{{ t('You are customising the survey for this project only.') }}</b>
                </p>
                <p>{{ t('Customisations you make in the following steps will <b> only affect the localised version of the survey used by your team.</b> The global survey selected/uploaded in Step 1 and shared with other teams will remain unchanged. Youi will be prompted to update the translation of your survey in future steps.') }}
                </p>
            </div>

            @php $currentTeam = \App\Services\HelperService::getCurrentOwner(); @endphp
            @if($currentTeam?->hddsModuleVersion())
                @php
                    $hddsHeading = t('Adapt HDDS hints');
                    $hddsDescription = t('Adjust the help text shown for Household Dietary Diversity questions for each language.');
                    $hddsUpdateLabel = t('Update');
                @endphp
                <x-rounded-section
                    :heading="$hddsHeading"
                    :description="$hddsDescription"
                    :buttonLabel="$hddsUpdateLabel"
                    :url="\App\Filament\App\Pages\PlaceAdaptations\HddsHints::getUrl()"/>
            @endif

            @if(ChoiceList::where('is_localisable', true)->where('has_custom_handling', false)->count() > 0)
                @php
                    $choiceListHeading = t('Contextualise choice lists');
                    $choiceListDescription = t('Adapt units, crops, and other choice list entries to be locally relevant.');
                    $updateLabel = t('Update');
                @endphp
                <x-rounded-section
                    :heading="$choiceListHeading"
                    :description="$choiceListDescription"
                    :buttonLabel="$updateLabel"
                    :url='\App\Filament\App\Clusters\Localisations::getUrl()'/>
            @endif


            @php
                $initialPilotHeading = t('Initial Pilot');
                $initialPilotDescription = t('Initial piloting should be conducted to check the sense and functionality of the survey.');
                $viewDetailsLabel = t('View details');
            @endphp
            <x-offline-action-section
                :heading="$initialPilotHeading"
                :description="$initialPilotDescription"
                :buttonLabel="$viewDetailsLabel"
                :url="\App\Filament\App\Pages\PlaceAdaptations\InitialPilot::getUrl()"/>

        </div>
    </div>

    <!-- Footer -->
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
