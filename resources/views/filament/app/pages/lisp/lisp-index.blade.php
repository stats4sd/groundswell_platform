<?php

use App\Filament\App\Pages\Lisp\LispIndicators;
use App\Filament\App\Pages\Lisp\LispWorkshop;
use App\Filament\App\Pages\SurveyDashboard;

$lispWorkshopUrl = LispWorkshop::getUrl();
$lispIndicatorsUrl = LispIndicators::getUrl();
$surveyDashboardUrl = SurveyDashboard::getUrl();
?>

<x-filament-panels::page class="px-12 h-full">

    @php $instructions = tfile('lisp'); @endphp

    <x-instructions-sidebar>

        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>

    </x-instructions-sidebar>

    <div class="container mx-auto xl:px-12 ">
        <div class="surveyblocks pr-10 pb-6">
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

            <x-offline-action-section :url="$lispWorkshopUrl">
                <x-slot:heading>Local indicator selection process (LISP) workshop</x-slot:heading>
                <x-slot:description>Guidance and materials to support teams with planning the workshop, including a template to collect the details of the indicators identified by the workshop; this template will be used to add these local indicators on the following page.</x-slot:description>
                <x-slot:buttonLabel>View details</x-slot:buttonLabel>

            </x-offline-action-section>

            <x-rounded-section :url="$lispIndicatorsUrl">
                <x-slot:heading>Customise indicators</x-slot:heading>
                <x-slot:description>Customise the indicators included in your survey based on the outcome of the LISP workshop. This includes options to map indicators identified during the workshop to existing available indicators as well as adding custom indicators and questions.</x-slot:description>
                <x-slot:buttonLabel>Update</x-slot:buttonLabel>
            </x-rounded-section>

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
