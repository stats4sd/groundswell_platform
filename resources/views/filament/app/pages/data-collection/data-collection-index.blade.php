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
    <x-instructions-sidebar>
        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>

            <div class="mx-12 mb-4">


                <p class="my-2">
                    {{ t("Once you have tested and finalised the details of your localised HOLPA survey, data collection may begin.") }}
                </p>
                    <h5>{{ t("Set up the survey") }}</h5>

                <p class="my-2">
                    {{ t("Up until this point, all the forms used for pilot testing have been labelled as \"test\" forms, and the submissions are stored as test data, not to be included in the results. To begin actual data collection, you need to use the \"Set up live forms for data collection\" section to set your survey to \"live\". There are some notes on this page to prompt you to double check all the necessary tasks have been completed. Read through these, and when you are ready, click the button to make your survey live.") }}
                </p>
                </p>

                 <p class="my-2">
                    {{ t("Once your survey is live, you will be able to use a QR code to set up new devices with the correct forms. Enumerators who have already joined the project using the QR code at the pilot phase can alternatively sync their devices to receive the updated forms.") }}
                {{-- Check the above text. --}}
                 <p class="my-2">
                    {{ t("Before enumerators commence data collection, they should double check that the forms on their ODK Collect app are indeed the live versions; test versions will be labelled as such in the form title, e.g. \"HOLPA Household Form - Local Shared Test Version\". Reminder: under no circumstances should enumerators use the draft versions of the survey forms accessed from the initial pilot section. The data from these is not saved and will be lost.") }}
                </p>
                 <p class="my-2">
                    {{ t("Once enumerators begin data collection, you will be able to see form submissions in the next section, Monitor data collection.") }}

                {{-- (Not sure if we actually need this section - obviously forms and submissions are on the next step.) --}}
                {{ t("At the bottom of the page, you can see the titles and published status of your forms. There are options to publish changes if this still needs to be done before commencing data collection.") }}
                </p>


                <h5>{{ t("Monitor data collection") }}</h5>
                <p class="my-2">

                {{ t("This page lets you see incoming data. You can track progress and review submissions for quality assurance purposes.") }}
                </p>
                <p class="my-2">
                {{ t("At the top of the page, you will see a general summary of the data that has been collected; this includes the number of submissions for each form and number of farms surveyed.") }}
                {{ t("Beneath that, you can browse the submissions. Use the tabs to view submissions by locations at different levels, or  to simply view all of them.") }}
                </p>
                <p class="my-2">
                {{ t("There is also an option to download the raw data from submissions. Note that this will be unprocessed, and will not include calculated indicators; to obtain survey data ready for analysis, use the \"data analysis\" section.") }}
                </p>
                <p class="my-2">
                {{ t("If you find you need to correct an error in the data, you can directly edit a submission. This should be used sparingly, only where it has been confirmed with an enumerator that something was inputted incorrectly.") }}

                </p>

                <h5>{{ t("Mark this section as complete when:") }}</h5>

                <p class="my-2">
                {{ t("Data collection has been carried out and concluded.") }}
                </p>
            </div>
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
