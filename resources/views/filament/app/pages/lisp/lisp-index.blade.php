<?php

use App\Filament\App\Pages\Lisp\LispIndicators;
use App\Filament\App\Pages\Lisp\LispWorkshop;
use App\Filament\App\Pages\SurveyDashboard;

$lispWorkshopUrl = LispWorkshop::getUrl();
$lispIndicatorsUrl = LispIndicators::getUrl();
$surveyDashboardUrl = SurveyDashboard::getUrl();
?>

<x-filament-panels::page class="px-12 h-full">

    <x-instructions-sidebar>

        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>
            {{-- <div class="pr-4 content-center mx-auto my-4">
            <iframe class="rounded-3xl" src="https://www.youtube.com/embed/TODO_ADD_VIDEO_ID" style="width: 560px; height: 315px;" frameborder="0" allowfullscreen></iframe>
            </div> --}}
            <div class="mx-12 mb-4">
                <h5>The local indicator selection process (LISP) workshop</h5>
                <p class="mb-2">
                    The LISP is a vital part of the localisation of the HOLPA tool. It involves conducting a workshop with local farmers and stakeholders to identify a set of local indicators to include in the HOLPA tool that could be used to monitor the types of changes they want to see in their farms and landscapes.
                </p>
                <p class="mb-2">
                    The LISP workshop page provides some guidance and materials to support teams with planning the workshop, but the details of this will be specific to each team and location. There is a template provided to collect the details of the indicators identified by the workshop; this template will be used to add these local indicators on the following page.
                </p>
                <h5>Customise indicators</h5>
                <p class="mb-2">
                    The customise indicators page allows you to incorporate the local indicators identified in your workshop into the customised HOLPA tool by uploading the local indicators, matching them where possible with available HOLPA indicators, and adding new custom indicators for any that remain.
                </p>
                <p class="mb-0">
                    When you go to the customise indicators page, you have three options. You will want to work left to right to start with, but you can return at any point and make changes in any of the sections. </p>
                <ul class="instructions_list list-disc mt-0 ">
                    <li>
                        <span class="font-semibold">Upload local indicators</span><br>
                        This is where you upload the completed template containing the local indicators that were selected in the workshop. Drag and drop or click to select the file you want to upload, then click the upload button to confirm. Once you have uploaded a file, you will see the details and have the option to delete it and upload a new file.
                    </li>
                    <li>
                        <span class="font-semibold">Match with global indicators</span>
                        <br>
                        This option allows you to browse through the list of core and optional indicators already present in the global HOLPA survey. If your indicators match up to the ones already available, you can add them easily by matching them here.
                        <br>
                        Click on an indicator on the left hand side. The available indicators within the corresponding theme will appear on the right. Read through and, if there is one that is the same as your indicator (or "close enough", depending on what your team and stakeholders may decide), then select that as a match.
                        Do this for each of the indicators. There may not be suitable matching indicators for all of them; remaining indicators can be incorporated into the survey using the "Add custom survey questions" option.
                    </li>
                    <li>
                        <span class="font-semibold">Add custom survey questions</span><br>
                        This may be more resource intensive in terms of data collection, and reduces the comparability of results, so consider each one you choose to add carefully as a compromise between local relevance and global comparability.
                        <br>
                        You will see the remaining unmatched indicators in a table. You have the option to either import questions in bulk using an XLSform, or manually add questions for each indicator.
                        <ul class="instructions_list list-disc ">
                            <li>
                                If you are confident writing ODK forms in Excel, them importing questions will give you more flexibility (for example, with different question types). To import questions, click the "Download XLSform template" button, and fill in the template. You will need to write your questions in a normal ODK XLSform format, and use the first column in the template to select which indicator each question is associated with. When you have completed the form, return to the same page to upload it, and you will see the newly added questions in the table below.
                            </li>
                            <li>
                                You can add questions manually using the "Add question" button for each indicator in the list. Select the question type, and then fill in the fields as needed. When you save changes to the question, it will appear in the list.
                            </li>
                            <li>
                                On the left hand side of the table, there is a button you can drag to reorder the questions within an indicator.
                            </li>
                        </ul>
                    </li>
                    <li>
                        <span class="font-semibold">Place custom questions in survey</span><br>
                        This option allows you to indicate where in the survey the custom questions should be placed. In the left column is a list of your local indicators, and you can click to drop down to see the questions you have added for each one. On the right hand side are all the modules of the Household and Fieldwork surveys. To place the questions, drag an indicator from the list on the left to the desired position in the list on the right.
                    </li>
                </ul>
                <h5> Mark this section as complete when</h5>
                <p class="mb-2">
                    You have carried out the LISP workshop, agreed the local indicators for your survey, and used the matching and custom question options to add those indicators to your survey.
                </p>

            </div>

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
