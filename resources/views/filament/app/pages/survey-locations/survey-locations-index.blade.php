<?php

use App\Filament\App\Clusters\LocationLevels\Resources\FarmResource;
use App\Filament\App\Pages\SurveyDashboard;

$farmUrl = FarmResource::getUrl();
$surveyDashboardUrl = SurveyDashboard::getUrl();
?>

<x-filament-panels::page class="h-full">

    <x-instructions-sidebar>
        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>

            {{-- <div class="pr-4 content-center  mx-auto my-4">
                <iframe class="rounded-3xl" src="https://www.youtube.com/embed/TODO_ADD_VIDEO_ID" style="width: 560px; height: 315px;" frameborder="0" allowfullscreen></iframe>
            </div> --}}
            <div class="mx-12 mb-4">
                <p class="mb-2">
                    To enable enumerators to conduct the survey, and possibly for data analysis later on, you will need to add details of the farms you will visit, including the details of the different location levels. The system by which geographic locations are organised and denoted will vary between places (some countries are organised into counties and towns, some have provinces and districts), so you will need to first input the details of the hierarchy, and then fill in the specific locations for your survey.
                </p>
                <p class="mb-2">
                    For example, a farm might be located in a village, which is in a district, which is in a province, so you would need to add the location levels province > district > village. Then you can add or import the full list of locations at each level and of the farms.
                </p>
                <h5>Location levels</h5>
                <p class="mb-2">
                    On the location levels page, you first need to add the names of the different levels:
                </p>
                <ul class="instructions_list list-disc ">
                    <li>
                        Click on "new location level".
                    </li>
                    <li>
                        Indicate whether the location level you are adding is a sub-level of another level - in our province > district > village example, district is a sub-level of province and village is a sub-level of district.
                    </li>
                    <li>
                        Add the name for the level.
                    </li>
                    <li>
                        Indicate whether there are farms at this level - This means that they are within this level but not in a lower sub-level; for example, there are farms at the village level but not the province level.
                    </li>
                    <li>
                        Click to create the level, or to create and immediately add another level.
                    </li>
                </ul>
                <p class="mb-2">
                    Once you have added levels, you can select them to view a list of the locations that have been added at that level and add locations. You have to option to import them from an excel file or add them manually.
                </p>
                <p class="mb-2">

                    To import locations, upload an excel spreadsheet with the required details:
                </p>
                <ul class="instructions_list list-disc ">
                    <li>
                        A column with the name of the location
                    </li>
                    <li>
                        A column with the unique IDs for the locations. (These can be generated however you like, but each one must be unique)
                    </li>
                    <li>
                        If the location level you are adding to is a sub-level of another location level, then you need to also have a column with the name and unique ID of the location in the level above (in our province > district > village example, "Village A" might be located in "District C", and you would need to include the details of both.)
                    </li>
                    <li>
                        The first row of the worksheet should contain column headings, not your first location.
                    </li>
                    <li>
                        Make sure the data to be imported is in the first worksheet of the spreadsheet.
                    </li>
                    <li>
                        You should have a column for location name and a column for unique codes for the locations.
                    </li>
                    <li>
                        It does not matter what order the columns are in, what column headers you use, or if other data is in the spreadsheet.
                    </li>
                </ul>
                <p class="mb-2">
                    Once you have uploaded the list, use the column mapping options to select the column for names and the column for unique code.

                    You have the option to override all previously added locations with the new locations from the uploaded spreadsheet. When you are ready, click submit.
                </p>
                <p class="mb-2">
                    To add locations manually, click on "Add new [location name]", and type in the name and code, then click on "Create" or "Create and create another". Add locations for for all the location levels.
                </p>

                <p class="mb-2">
                <h5>List of farms</h5>
                <p class="mb-2">
                    The next action is to add the details of all the farms that you will visit in your survey. Similar to adding locations, you can add farms manually or import a list from a spreadsheet file. The spreadsheet should be set up with the same structure as when adding locations at other levels.
                </p>
                <p class="mb-2">
                    When importing a list, you will need to include columns with the farm unique code, and the unique code for the location it is in. When you upload the list, you need to select the location level the farms are located in. You can also optionally include extra columns with details that identify the farm, such as farm name, telephone number, family name etc, or for details that contain properties of the farm which may be useful for future analysis, such as the size of the farm.
                </p>

                <h5>Mark this section as complete when: </h5>
                <p class="mb-2">
                    You have added all the location levels, locations, and a full list of the farms where you will conduct the survey.
                </p>

            </div>
        </x-slot:instructions>
    </x-instructions-sidebar>

    <div class="container mx-auto xl:px-12 ">
        <div class="surveyblocks pr-10 pt-8">

            <x-rounded-section
                heading='Manage location levels'
                description='Manage the location levels (or other strata) in your sampling frame.'
                buttonLabel='Update'
                url='location-levels'
            />

            <x-rounded-section
                heading='List of farms'
                description='Add or import details of the farms you will visit to give the questionnaire.'
                buttonLabel='Update'
                :url='$farmUrl'
            />

            <x-rounded-section
                heading="Context Questions"
                description="Optionally, add extra questions to the survey to get additional context about the farms."
                button-label="Manage Questions"
                :url=" \App\Filament\App\Pages\SurveyLocations\ContextQuestions::getUrl()"
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
