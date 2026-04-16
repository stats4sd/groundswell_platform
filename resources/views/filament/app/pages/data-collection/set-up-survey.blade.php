<?php

use App\Filament\App\Pages\SurveyDashboard;

$surveyDashboardUrl = SurveyDashboard::getUrl();
?>
<x-filament-panels::page class="px-10 h-full">

    <x-instructions-sidebar>
        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>

            <div class="mx-12 mb-4">
                <p class="my-2">
                    Once you have tested and finalised the details of your localised HOLPA survey, data collection may begin.
                </p>
                <h5>Set up live forms for data collection </h5>

                <p class="my-2">
                    Up until this point, all the forms used for pilot testing have been labelled as "test" forms, and the submissions are stored as test data, not to be included in the results. To begin actual data collection, you need to use this section to set your survey to "live". There are some notes on this page to prompt you to double check all the necessary tasks have been completed. Read through these, and when you are ready, click the button to make your survey live.
                </p>
                <h5>Access live forms </h5>
                <p class="my-2">
                    Once your survey is live, this section will display the QR code to set up new devices with the correct forms. Enumerators who have already joined the project using the QR code at the pilot phase can alternatively sync their devices to receive the updated forms.
                {{-- Check the above text. --}}
                <p class="my-2">
                    Before enumerators commence data collection, they should double check that the forms on their ODK Collect app are indeed the live versions; test versions will be labelled as such in the form title, e.g. "HOLPA Household Form - Local Shared Test Version". Reminder: under no circumstances should enumerators use the draft versions of the survey forms accessed from the initial pilot section. The data from these is not saved and will be lost.
                </p>
                <p class="my-2">
                    Once enumerators begin data collection, you will be able to see form submissions in the next section,
                    <a href="{{ \App\Filament\App\Pages\DataCollection\MonitorDataCollection::getUrl() }}" class="font-semibold text-green">Monitor data collection</a>.
                </p>

                <h5>Forms overview</h5>
                <p class="my-2">
                    Here you can see the titles and published status of your forms. There are options here to publish changes if this still needs to be done before commencing data collection.
                </p>
            </div>
        </x-slot:instructions>
    </x-instructions-sidebar>

    @if(!$team->ready_for_live)
        <x-red-alert-box class="-top-10">
            <x-slot:content>
                There are still localisation steps that are incomplete. Please review the dashboard for steps marked as 'not started' or 'in progress', and complete them before continuing.
            </x-slot:content>
        </x-red-alert-box>
    @endif

    <div class="container mx-auto xl:px-12">
        <div class="surveyblocks p-8 md:p-16">

            @if(!$team->pilot_complete)
                <div class="" id="testmode">
                    <h3 class="mb-4">Set up live forms for data collection</h3>
                    <p class="mb-2">
                        Your team is currently in
                        <span class="font-semibold text-green ">pilot testing mode</span>. To switch to live data collection, please confirm that your team is ready to commence live data collection. This means:

                    </p>
                    <ul class="mb-2 ml-12 list-disc">
                        <li class="mb-1">
                            All the required changes to the survey have been made and published.<br>
                            {{-- If there are unpublished changes! --}}
                            <span class="text-orange"> One or more of your forms has changes that have not been published. This may be intentional, but take the time to check that you have published all the required changes before continuing. <a class="font-semibold text-red" href="{{ \App\Filament\App\Pages\Pilot\PilotIndex::getUrl() }}#forms">Click here</a> to return to the pilot page and review the status of the forms. </span>
                            {{-- / If there are unpublished changes! --}}
                        </li>
                        <li class="mb-1">
                            All rounds of pilot testing and subsequent adjustments have been concluded.
                        </li>
                        <li class="mb-1">
                            All enumerators have ceased test or pilot submissions, and all subsqeuent form submissions are to be considered real survey data (until you revert to testing mode, if you choose to do so).
                        </li>
                    </ul>
                    <p class="mb-2">
                        If the above statements are true, click the button below to start live data collection.
                    </p>
                    <div class="pt-8 mb-8 w-full text-center">

                        {{ $this->markPilotCompleteAction }}
                    </div>
                    <div class="mb-12">
                        <h3 class="mb-4" id="qr"> Access live forms</h3>
                        <div class="border border-gray-200 bg-gray-50 p-8 md:mx-24">
                            <p class="">You will be able to access the live forms once you have confirmed that you are ready to commence live data collection. Please see the above section or page instructions for more information.</p>
                        </div>

                    </div>
                </div>
            @else
                <div class="" id="livemode">

                    <div class="mb-8">
                        <h3 class="mb-4" id="qr"> Access live forms</h3>

                        <div class="flex flex-row">


                            <div class="basis-3/4 pr-12">
                                <p class="mb-2">
                                    To link your Android device, install and open
                                    <b>ODK Collect</b>. When asked for project details, scan the QR code on this page. Your device will be linked and you will have access to the forms listed below. Enumerators who have already joined the project using the QR code at the pilot phase will automatically receive the latest live forms.
                                </p>
                                <p class="my-2">
                                    Once enumerators begin data collection, you will be able to see form submissions on the
                                    <a href="{{ \App\Filament\App\Pages\DataCollection\MonitorDataCollection::getUrl() }}" class="text-green font-semibold">Monitor data collection</a> page.
                                </p>
                            </div>
                            <div class="mr-4 text-center basis-1/4  rounded-lg px-4 bg-white flex flex-col justify-start space-y-4">
                                <div class="mx-auto">{{ QrCode::size(150)->generate(\Stats4sd\FilamentOdkLink\Services\HelperService::getCurrentOwner()->odk_qr_code) }}</div>
                                <h5 class="">SCAN QR Code in ODK Collect</h5>
                            </div>
                        </div>


                    </div>
                    <h3 class="mb-4">Your survey is live</h3>
                    <p class="mb-2">
                        Your team's survey is currently set to
                        <span class="font-semibold">live data collection</span>. To switch back to testing mode, click the button below. If you do this, remember to return to this page and switch back again before resuming data collection.

                    </p>

                    <div class="pt-8 mb-8 w-full text-center">
                        {{ $this->markPilotIncompleteAction }}
                    </div>
                </div>

                <h3 class="mb-6" id="forms"> Forms overview</h3>
                <livewire:xlsforms-table-view/>
            @endif


        </div>
    </div>

</x-filament-panels::page>
