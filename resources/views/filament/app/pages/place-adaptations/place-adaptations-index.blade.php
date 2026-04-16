<?php

use App\Filament\App\Pages\SurveyDashboard;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;

$surveyDashboardUrl = SurveyDashboard::getUrl();
?>

<x-filament-panels::page class="px-10 h-full">

    <x-instructions-sidebar>
        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>

            {{-- <div class="pr-4 content-center  mx-auto my-4">
                <iframe class="rounded-3xl" src="https://www.youtube.com/embed/TODO_ADD_VIDEO_ID" style="width: 560px; height: 315px;" frameborder="0" allowfullscreen></iframe>
            </div> --}}
            <div class="mx-12 mb-4">
                <p class="mb-2">
                    The localisation sections allow you to adjust the HOLPA survey to ensure it is relevant to the target audience. Customisations you make in the following steps will only affect the localised version of the survey used by your team. The global survey translation selected or uploaded in Step 1 and shared with other teams will remain unchanged.
                </p>
                <p class="mb-2">
                    In this first section "Place-based adaptations", you can customise certain questions and answer options. For example, in different geographical locations, farmers would be growing different crops and different staple foods would be commonly consumed; the options in the questionnaire should reflect this.
                </p>
                <h5>Time frame </h5>
                <p class="mb-2">

                    The first thing you can customise is the time frame that is asked about for questions concerning the recent past. By default, this time frame is "In the last 12 months". However, for your survey, it might make more sense to ask the questions about "last season" or "last year".
                </p>
                <p class="mb-2">
                    The page shows the questions in the survey that use the time frame. Whatever phrase is used for the time frame will be inserted into the question in place of the "${time_frame}"" text placeholder. Look through them, determine what time frame is most appropriate and, if you decide to change it, update the timeframe text in the box. Your entry will be automatically saved and the question text in your survey form will be updated.
                </p>
                <h5>Diet Diversity module </h5>

                <p class="mb-2">
                    HOLPA uses an internationally validated indicator for "dietary diversity". The questions in this section ask whether members of the household have consumed anything from specific food groups within the last 24 hours, such as grain food, tubers, pulses, green veg, etc. The default survey has all the needed questions, but does not include lists of locally contextualised example foods for each group.
                </p>
                <p class="mb-2">
                    The platform can incorporate localised versions of the questions from the
                    <a href="https://www.dietquality.org/tools" class="text-green font-semibold">Global Diet Quality Project</a>, which add relevant example foods for each category customised for over 100 countries. If you would like to include these in your survey, select the suitable country from the list of available countries. The page shows the questions that will appear in the survey, so you can review the default and the localised versions with examples, and decide what to use for your survey.
                </p>
                <h5>Contextualise choice lists</h5>

                <p class="mb-2">
                    There are some questions in the survey where the appropriate answer options will be different depending on the location context - for example, questions that ask about crops that are grown on a farm
                    <i>should not</i> include lots of options for plants that do not grow in the location being surveyed, and
                    <i>should</i> include the most commonly grown crops in that area. Questions should also reflect the units of measurement that are used in the location.
                </p>
                <p class="mb-2">
                    The "Contextualise choice lists" has several choice lists to be checked and customised. You can select from the lists on the left hand side, then review the existing options. Options can be removed from the context, so they will not be included in this questionnaire, and you have the option to add new options by clicking the "add new" button. For each choice list entry, you will need to add a name and label, then click "create", or "create and add another" to save the entry.
                </p>

                <p class="mb-2">
                    Each list page includes the option to view the questions that will use these answer options. Check these to ensure you provide suitable options.
                </p>

                <h5>Pilot test</h5>
                <p class="mb-2">
                    Following customisation, a pilot test should be conducted to check the sense and functionality of the survey. The initial pilot page contains more detailed guidance on this process, and this is where you can find the QR code to scan to start testing the survey using ODK.

                </p>
                <p class="mb-2">
                    Note that this QR code should only be used for this initial testing - the 'data collection' section contains a different link for the full pilot and live data collection, and it is important to use the correct version of the survey.
                </p>


                <h5>Mark this section as complete when: </h5>
                <p class="mb-2">
                    You have made all the desired adaptions to the details available for change in this step, piloted the full survey with a local researcher or practitioner, and made any needed adjustments.
                </p>

            </div>
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
                <p class="font-bold text-green text-lg pb-4">CUSTOM SURVEY</p>
                <p>
                    <b>You are customising the survey for this project only.</b>
                </p>
                <p>Customisations you make in the following steps will
                    <b>only affect the localised version of the survey used by your team.</b>
                    The global survey selected/uploaded in Step 1 and shared with other teams will remain unchanged. Youi will be prompted to update
                    the translation of your survey in future steps.
                </p>
            </div>
            <x-rounded-section
                heading='Adapt time frame'
                buttonLabel='Update'
                :url='\App\Filament\App\Pages\PlaceAdaptations\TimeFrame::getUrl()'>
                <x-slot:description>Some questions in the Household Survey ask about a specific time frame in the recent past. You may customise this to your specific requirements.
                </x-slot:description>
            </x-rounded-section>

            <x-rounded-section
                heading='Adapt Diet Quality Module'
                buttonLabel='Update'
                :url='\App\Filament\App\Pages\PlaceAdaptations\DietDiversity::getUrl()'/>
            <x-slot:description>HOLPA uses an international standard "Diet Quality" module. This module is available for over 100 countries. We recommend you select the version most suited to your context.
            </x-slot:description>

            @if(ChoiceList::where('is_localisable', true)->where('has_custom_handling', false)->count() > 0)
                <x-rounded-section
                    heading='Contextualise choice lists'
                    description='Adapt units, crops, and other choice list entries to be locally relevant..'
                    buttonLabel='Update'
                    :url='\App\Filament\App\Clusters\Localisations::getUrl()'/>
            @endif


            <x-offline-action-section
                heading='Initial Pilot'
                description='Initial piloting should be conducted to check the sense and functionality of the survey.'
                buttonLabel='View details'
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
