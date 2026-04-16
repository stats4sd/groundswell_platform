<?php

use App\Filament\App\Pages\SurveyDashboard;

$surveyDashboardUrl = SurveyDashboard::getUrl();
?>
<x-filament-panels::page class="px-10 h-full">

    <x-instructions-sidebar>
        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>

            <div class="mx-12 mb-4">

                <h5 class="mt-0">Pilot test and enumerator training</h5>
                <p class="my-2">
                    This page guides you through the process of conducting the pilot test for your HOLPA survey.
                    Before you conduct the pilot, ensure you have:
                </p>
                <ul class="mb-2 ml-12 list-disc">
                    <li class="mb-1">
                        Made your intended changes and customisations using the previous steps on the dashboard.
                    </li>
                    <li class="mb-1">
                        As needed, used the initial pilot draft versions of the form to check any additional changes, such as the addition of custom questions for your local indicators following the LISP workshop.
                    </li>
                    <li class="mb-1">
                        Published all changes to update the forms you will use in the pilot.
                        <br>This must be done manually; this is so that you can make changes and test them as draft versions to confirm they are working as expected before updating the versions that your enumerator team will see. You will find the option to publish your changes on the "Access forms" section of the page.
                    </li>
                </ul>
                <p class="mb-2">
                    When you are ready for the pilot, you will need to organise the workshop as described in this page. There are instructions here, as well as in the HOLPA Guidance. You will find the QR code to allow enumerators to set up the forms on the "Access forms" section of the page. Note that these are not the same as the draft versions used in the initial pilot, and those draft forms should never be used for this pilot or for live data collection, as the data submitted for those forms is not saved. Ensure that everyone is using the correct version of the forms before proceeding with the pilot or any data collection.
                </p>

                <h5 class="mt-0">Access forms</h5>
                <p class="mb-2">
                    The "Access forms" section contains the option to publish your changes to the survey, and QR codes to set up the forms on the mobile devices that will be used to pilot data collection.
                </p>

                <h5 class="mt-0">Forms and submissions</h5>
                <p class="mb-2">
                    In the "Forms and submissions" section, you can see an overview of the current forms and view the submissions. If new submissions have not yet appeared on the page, you can use the "manually get submissions" option on the survey forms view to prompt this to refresh.
                </p>
                <p class="mb-2">
                    The survey submissions view shows you all the submissions for your forms. You can view individual submissions to conduct quality checks. This will help you identify frequent errors or omissions which may necessitate further enumerator training or adjustments to the form.
                </p>
                <p class="mb-2">
                    When you have completed the pilot, you will likely then need to return to previous sections to make additional edits to the survey. Revisit any section from the dashboard as needed. You may choose to carry out further pilots or technical tests before commencing live data collection.
                </p>
                <h5> Mark this section as complete when:</h5>
                <p class="mb-2">
                    You have conducted the pilot and training workshop, and are satisfied that the required adjustments have been made.
                </p>

            </div>

        </x-slot:instructions>
    </x-instructions-sidebar>

    <div class="container mx-auto xl:px-12">
        <div class="surveyblocks p-12 lg:px-16">

            @if($team->pilot_complete)
                <div class="border border-gray-200 bg-gray-50 p-8 md:mx-24">
                    <p class="">Your team has begun live data collection, which means pilot testing is no longer available. If you need to review changes to your forms before publishing them, you can review the DRAFT VERSIONS on the
                        <a class="font-semibold text-green hover:underline">Initial Pilot Page</a>.
                    </p>
                    <p class="mt-2">If you have started live data collection by mistake and need to return to the pilot test, click below.</p>

                    <div class="mt-4 text-center">
                        {{ $this->markPilotIncompleteAction }}
                    </div>
                </div>

                <div class="mt-10">
                    <livewire:submissions-table-view :visible="true" :test="true"/>
                </div>
            @else
                <div class="mb-10">
                    <div class="mb-10">
                        <div class="w-full text-center mb-8">
                            <a class="buttona" href="#qr">Jump to access forms</a>
                        </div>
                        <h3 class="mb-4"> Pilot test and enumerator training</h3>

                        <p class="mb-2">
                            Once you have completed the localisations and any changes to the survey and translations, it is time to conduct a full pilot and enumerator training. This step familiarises the enumerators with the survey, preparing them to use the questionnaires, and allows for quality control and testing of the survey with the people and context for which it will need to work in the live data collection.
                        </p>
                        <p class="mb-2">
                            The enumerator training includes three stages: </p>
                        <ol class="mb-4 ml-12 list-decimal">

                            <li class="mb-1">
                                <span class="font-semibold">Survey review: </span>
                                Enumerator training begins with an in-person workshop to go through each of the survey questions. The objective is for enumerators to understand the purpose of the survey and the information required by each question, to validate the functionality of the survey on their devices and to simulate survey implementation.
                            </li>
                            <li class="mb-1">

                                <span class="font-semibold">Piloting and feedback: </span>
                                Enumerators then carry out a full pilot test with local farmers. The objective is to identify difficult or unclear questions and any technological errors in the digital survey.

                                For piloting and data collection, enumerators will need an android device with ODK collect installed and set up. They can then use the QR code below to access the latest published version of the form. Take note of the titles of the forms in the app - ensure nobody is accidentally using an old version of the form, or the incomplete version used in the initial pilot test.

                            </li>
                            <li class="mb-1">
                                <span class="font-semibold">Quality control: </span>
                                In this stage, the data collected during the pilot is reviewed for quality assurance, and issues can be identified.
                                You can view a summary of submitted data and view each submission to conduct quality checks. This will help you identify frequent errors or omissions which may necessitate further enumerator training or adjustments to the form.

                            </li>
                        </ol>
                        <p class="mb-2">
                            During the piloting and training, special attention should be paid to correct interpretation of questions related to social and environmental dimensions, which include perception questions and field work.
                        </p>
                        <p class="mb-2">
                            Once you have carried out the pilot and training, you will likely need to return to the dashboard options to make additional edits to the survey based on the feedback and findings gleaned from the pilot. Ensure you use the "publish" button to apply your changes to the published version of the form.
                        </p>
                    </div>
                    <div class="mb-12">
                        <h3 class="mb-4" id="qr"> Access forms</h3>

                        <div class="flex flex-row">


                            <div class="basis-3/4 pr-12">
                                Your project team has been set up. To link your Android device, install and open
                                <b>ODK Collect</b>. When asked for project details, scan the QR code on this page. Your device will be linked and you will have access to the forms listed below.

                                @if($team->xlsforms->some('live_needs_update'))
                                    <div class="my-4  bg-red-100 border-2 border-red-700 text-red-700 px-4 py-3 rounded-xl relative flex-col" role="alert">
                                        <div class=" flex flex-col md:flex-row items-center gap-4">

                                            <x-heroicon-o-exclamation-triangle class="w-12 sm:w-16 flex-shrink-0 text-red mb-2 md:mb-0"/>

                                            <p>
                                                One or more of your forms has changes that have not been published. These changes will not be reflected in the forms used for the pilot test. You can review the form details and and publish changes as necessary using the table below.
                                            </p>
                                        </div>
                                        <div class="w-full text-center mt-6 mb-2">
                                            <a class="buttona !bg-red-600 hover:!bg-red-800" href="#forms"> Review and publish</a>
                                        </div>

                                    </div>
                                @endif

                                When you make changes to the forms on the platform, they are not automatically updated on your device. This is so that you can make changes, test them as
                                <a class="font-semibold text-green" href="{{ \App\Filament\App\Pages\PlaceAdaptations\InitialPilot::getUrl() }}">DRAFT VERSIONS</a> and confirm they are working as expected before updating the versions that your enumerator team will see.

                                <br/><br/>

                                If there are changes that can be published, you can do so by clicking the
                                <b>Publish</b> button on the table below. We highly recommend reviewing the forms as DRAFT versions before publishing. You can do so on the
                                <a class="font-semibold text-green" href="{{ \App\Filament\App\Pages\PlaceAdaptations\InitialPilot::getUrl() }}">Initial Pilot Page</a>.

                            </div>
                            <div class="mr-4 text-center basis-1/4  rounded-lg px-4 bg-white flex flex-col justify-start space-y-4">
                                <div class="mx-auto">{{ QrCode::size(150)->generate(\Stats4sd\FilamentOdkLink\Services\HelperService::getCurrentOwner()->odk_qr_code) }}</div>
                                <h5 class="">SCAN QR Code in ODK Collect</h5>
                            </div>
                        </div>


                    </div>
                </div>
                <h3 class="mb-4" id="forms"> Forms and submissions</h3>
                <div class="flex justify-center mb-8">

                    <x-filament::tabs>
                        <x-filament::tabs.item wire:click="$set('tab', 'xlsforms')" :active="$tab === 'xlsforms'">
                            Survey Forms
                        </x-filament::tabs.item>
                        <x-filament::tabs.item wire:click="$set('tab', 'submissions')" :active="$tab === 'submissions'">
                            Pilot Test Submissions
                        </x-filament::tabs.item>
                    </x-filament::tabs>
                </div>

                @if ($tab === 'xlsforms')
                    <livewire:xlsforms-table-view/>
                @elseif ($tab === 'submissions')
                    <livewire:submissions-table-view :visible="true" :test="true"/>
                @endif

                <h3 class="mt-10 mb-4">Begin Live Data Collection</h3>
                <p>When you have completed the pilot test and are ready to begin live data collection, click below. This will disable this page and mark all future submissions as "live" data.</p>

                <div class="mt-4 text-center">
                    {{ $this->markPilotCompleteAction }}
                </div>

            @endif
        </div>
    </div>

    <x-filament-actions::modals/>


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
