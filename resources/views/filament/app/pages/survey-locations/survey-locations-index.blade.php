<?php

    use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource;
    use App\Filament\App\Pages\SurveyDashboard;

    // Points at the new ODK-Entities-backed Farms page - the original FarmResource is
    // kept around (unlinked) for comparison during the migration. See
    // docs/plans/odk-entities-farm-crud.md.
    $farmUrl = FarmEntityResource::getUrl();
    $surveyDashboardUrl = SurveyDashboard::getUrl();

    $manageLocationLevelsHeading = t("Manage location levels");
    $manageLocationLevelsDescription = t("Manage the location levels (or other strata) in your sampling frame.");

    $listOfFarmsHeading = t("List of farms");
    $listOfFarmsDescription = t("Add or import details of the farms you will visit to give the questionnaire.");

    $contextQuestionsHeading = t("Context Questions");
    $contextQuestionsDescription = t("Optionally, add extra questions to the survey to get additional context about the farms.");

    $updateLabel = t("Update");
    $manageQuestionsLabel = t("Manage Questions");

    $instructions = tfile('survey-locations');
?>

<x-filament-panels::page class="h-full">

    <x-instructions-sidebar>
        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>
    </x-instructions-sidebar>

    <div class="container mx-auto xl:px-12 ">
        <div class="surveyblocks pr-10 pt-8">

            <x-rounded-section
                :heading="$manageLocationLevelsHeading"
                :description="$manageLocationLevelsDescription"
                :buttonLabel="$updateLabel"
                url="location-levels"
            />

            <x-rounded-section
                :heading="$listOfFarmsHeading"
                :description="$listOfFarmsDescription"
                :buttonLabel="$updateLabel"
                :url="$farmUrl"
            />

            <x-rounded-section
                :heading="$contextQuestionsHeading"
                :description="$contextQuestionsDescription"
                :button-label="$manageQuestionsLabel"
                :url="\App\Filament\App\Pages\SurveyLocations\ContextQuestions::getUrl()"
            />
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
