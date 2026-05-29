<?php
    use App\Filament\App\Pages\SurveyDashboard;
    $surveyDashboardUrl = SurveyDashboard::getUrl();

    $setupSurveyHeading = t("Set up the survey");
    $setupSurveyDescription = t("Manage the ODK survey to be used for data collection");
    
    $updateLabel = t("Update");

    $monitorHeading = t("Monitor Data Collection");
    $monitorDescription = t("Review and quality-check incoming data");

?>

<x-filament-panels::page class="h-full">
    @php $instructions = tfile('data-collection'); @endphp

    <x-instructions-sidebar>
        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>
    </x-instructions-sidebar>
    <div class="container mx-auto xl:px-12 ">
        @if(!$team->ready_for_live)
            <x-red-alert-box class="-top-10">
                <x-slot:content>
                    {{ t("There are still localisation steps that are incomplete. Please review the dashboard for steps marked as 'not started' or 'in progress', and complete them before continuing.") }}
                </x-slot:content>
            </x-red-alert-box>
        @endif
        <div class="surveyblocks pr-10  pt-8">

            <x-rounded-section
                :heading="$setupSurveyHeading"
                :description="$setupSurveyDescription"
                :buttonLabel="$updateLabel"
                :url="\App\Filament\App\Pages\DataCollection\SetUpSurvey::getUrl()"
            />

            <x-rounded-section
                :heading="$monitorHeading"
                :description="$monitorDescription"
                :buttonLabel="$updateLabel"
                :url="\App\Filament\App\Pages\DataCollection\MonitorDataCollection::getUrl()"
            />

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
