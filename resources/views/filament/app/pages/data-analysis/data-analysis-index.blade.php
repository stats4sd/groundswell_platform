<?php

use App\Filament\App\Pages\SurveyDashboard;

$surveyDashboardUrl = SurveyDashboard::getUrl();
?>

<x-filament-panels::page class="h-full">

<x-instructions-sidebar>
        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>

            <div class="mx-12 mb-4">
                <p class="mb-2">
In this section, you can download the dataset for your survey. The download will include all the data from the live data collection, some calculated agroecology and performance indicators, and a data dictionary.
From here, your team can conduct whatever data analysis is needed, and organise storing and sharing of the data as required.
            </p>


        </div>
    </x-slot:instructions>
</x-instructions-sidebar>



    <div class="container mx-auto  ">
        <div class="surveyblocks pt-16 pb-24 mb-32 px-12 lg:px-16">
            <h3 class="mb-6">Download data for analysis </h3>
            <p class="mb-4">
                Here, you can download the complete dataset for your survey. The download will be an .xlsx file containing:
            </p>
            <ul class="list-disc ml-6">
                <li class="mb-1">
                    All the data from the live data collection.
                </li>
                <li class="mb-1">
                    Agroecology and performance indicators, automatically calculated at farm level.
                </li>
                <li class="mb-4">
                    A detailed data dictionary to help you navigate through the data.
                </li>
            </ul>
            <p class="mb-8">
                Once you have downloaded the data, you can conduct data analysis as required, and it is up to your team or organisation to manage storage and sharing of the dataset as appropriate. Download the complete dataset from your survey. This export includes the agro-ecology and performance indicators, automatically calculated at farm-level, along with a detailed data dictionary to help you navigate through the data.
            </p>
{{-- This was the previous export data button - I wanted to make it look like the other downloads from other sections (I think just the workshop section) but couldn't figure out how to make that work so ....--}}
                 {{-- {{ $this->exportDataAction }} --}}

                <x-download-section :url="url('#')">
                    <x-slot:heading>Survey Dataset</x-slot:heading>
                        <x-slot:description>Download the complete dataset from your survey. This export includes calculated agro-ecology and performance indicators and a detailed data dictionary.</x-slot:description>
                        <x-slot:buttonLabel>Download .xlsx</x-slot:buttonLabel>
                </x-download-section>


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
