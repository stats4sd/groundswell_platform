<?php

use App\Filament\App\Pages\SurveyDashboard;

$surveyDashboardUrl = SurveyDashboard::getUrl();

$instructions = tfile('setup-survey');

?>
<x-filament-panels::page class="px-10 h-full">

    <x-instructions-sidebar>
        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>
    </x-instructions-sidebar>

    @if(!$team->ready_for_live)
        <x-red-alert-box class="-top-10">
            <x-slot:content>
                {{ t("There are still localisation steps that are incomplete. Please review the dashboard for steps marked as 'not started' or 'in progress', and complete them before continuing.") }}
            </x-slot:content>
        </x-red-alert-box>
    @endif

    <div class="container mx-auto xl:px-12">
        <div class="surveyblocks p-8 md:p-16">

            @if(!$team->pilot_complete)
                <div class="" id="testmode">
                    <h3 class="mb-4">{{ t("Set up live forms for data collection") }}</h3>
                    <p class="mb-2">
                        {{ t("Your team is currently in") }}
                        <span class="font-semibold text-green ">{{ t("pilot testing mode") }}</span>. {{ t("To switch to live data collection, please confirm that your team is ready to commence live data collection. This means:") }}

                    </p>
                    <ul class="mb-2 ml-12 list-disc">
                        <li class="mb-1">
                            {{ t("All the required changes to the survey have been made and published.") }} <br>
                            {{-- If there are unpublished changes! --}}
                            <span class="text-orange"> {{ t("One or more of your forms has changes that have not been published. This may be intentional, but take the time to check that you have published all the required changes before continuing.") }} <a class="font-semibold text-red" href="{{ \App\Filament\App\Pages\Pilot\PilotIndex::getUrl() }}#forms">{{ t("Click here") }}</a> {{ t("to return to the pilot page and review the status of the forms.") }} </span>
                            {{-- / If there are unpublished changes! --}}
                        </li>
                        <li class="mb-1">
                            {{ t("All rounds of pilot testing and subsequent adjustments have been concluded.") }}
                        </li>
                        <li class="mb-1">
                            {{ t("All enumerators have ceased test or pilot submissions, and all subsequent form submissions are to be considered real survey data (until you revert to testing mode, if you choose to do so).") }}
                        </li>
                    </ul>
                    <p class="mb-2">
                        {{ t("If the above statements are true, click the button below to start live data collection.") }}
                    </p>
                    <div class="pt-8 mb-8 w-full text-center">

                        {{ $this->markPilotCompleteAction }}
                    </div>
                    <div class="mb-12">
                        <h3 class="mb-4" id="qr">{{ t("Access live forms") }}</h3>
                        <div class="border border-gray-200 bg-gray-50 p-8 md:mx-24">
                            <p class="">{{ t("You will be able to access the live forms once you have confirmed that you are ready to commence live data collection. Please see the above section or page instructions for more information.") }}</p>
                        </div>

                    </div>
                </div>
            @else
                <div class="" id="livemode">

                    <div class="mb-8">
                        <h3 class="mb-4" id="qr">{{ t("Access live forms") }}</h3>

                        <div class="flex flex-row">


                            <div class="basis-3/4 pr-12">
                                <p class="mb-2">
                                    {{ t("To link your Android device, install and open") }}
                                    <b>{{ t("ODK Collect") }}</b>. {{ t("When asked for project details, scan the QR code on this page. Your device will be linked and you will have access to the forms listed below. Enumerators who have already joined the project using the QR code at the pilot phase will automatically receive the latest live forms.") }}
                                </p>
                                <p class="my-2">
                                    Once enumerators begin data collection, you will be able to see form submissions on the
                                    <a href="{{ \App\Filament\App\Pages\DataCollection\MonitorDataCollection::getUrl() }}" class="text-green font-semibold">{{ t("Monitor data collection") }}</a> {{ t("page.") }}
                                </p>
                            </div>
                            <div class="mr-4 text-center basis-1/4  rounded-lg px-4 bg-white flex flex-col justify-start space-y-4">
                                <div class="mx-auto">{{ QrCode::size(150)->generate(\Stats4sd\FilamentOdkLink\Services\HelperService::getCurrentOwner()->odk_qr_code) }}</div>
                                <h5 class="">{{ t("SCAN QR Code in ODK Collect") }}</h5>
                            </div>
                        </div>


                    </div>
                    <h3 class="mb-4">{{ t("Your survey is live") }}</h3>
                    <p class="mb-2">
                        {{ t("Your team's survey is currently set to") }}
                        <span class="font-semibold">{{ t("live data collection") }}</span>. {{ t("To switch back to testing mode, click the button below.") }} {{ t("If you do this, remember to return to this page and switch back again before resuming data collection.") }}
                    </p>

                    <div class="pt-8 mb-8 w-full text-center">
                        {{ $this->markPilotIncompleteAction }}
                    </div>
                </div>

                <h3 class="mb-6" id="forms"> {{ t("Forms overview") }}</h3>
                <livewire:xlsforms-table-view/>
            @endif


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
