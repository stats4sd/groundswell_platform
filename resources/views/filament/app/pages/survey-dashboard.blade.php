<?php

    use App\Filament\App\Pages\DataAnalysis\DataAnalysisIndex;
    use App\Filament\App\Pages\DataCollection\SetUpSurvey;
    use App\Filament\App\Pages\DataCollection\MonitorDataCollection;
    use App\Filament\App\Pages\Lisp\OptionalModules;
    use App\Filament\App\Pages\Pilot\PilotIndex;
    use App\Filament\App\Pages\PlaceAdaptations\PlaceAdaptationsIndex;
    use App\Filament\App\Pages\SurveyLocations\SurveyLocationsIndex;

    $instructions = tfile('survey-dashboard');

?>

<x-filament-panels::page>

    <x-instructions-sidebar :videoUrl="'#'">
        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>
    </x-instructions-sidebar>

    <div id="surveydash">
        <!-- Main Section -->
        <div class="container mx-auto xl:px-24">
            <div class="grid xl:grid-cols-12 gap-6 xl:mx-3">

                <!-- Context card -->
                <div class="flex flex-col lg:flex-row drop-shadow-lg overflow-hidden lg:h-72 col-span-12 lg:col-span-6">
                    <div class="greensection">
                        <img src="/images/context_icon.png" alt="Context Icon" class="w-8 mb-2 ml-8 lg:ml-0">
                        <div class="w-3/4 mx-10 lg:w-full lg:mx-0 lg:text-center">
                            <span class="mt-2 text-center">{{ t('Prepare survey') }}</span>
                            <!-- Progress bar -->
                            @if ($team->languages_progress === 'not_started')
                                <div class="w-3/4 bg-white bg-opacity-50 rounded-full h-2.5 mt-8 lg:mx-auto">
                                    <div class="bg-white h-2.5 rounded-full w-1/12"></div>
                                </div>
                            @elseif ($team->languages_progress === 'in_progress')
                                <div class="w-3/4 bg-white bg-opacity-50 rounded-full h-2.5 mt-8 lg:mx-auto">
                                    <div class="bg-white h-2.5 rounded-full w-6/12"></div>
                                </div>
                            @elseif ($team->languages_progress === 'complete')
                                <div class="w-3/4 bg-white bg-opacity-50 rounded-full h-2.5 mt-8 lg:mx-auto">
                                    <div class="bg-white h-2.5 rounded-full w-full"></div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="whitesection">
                        <div class=" whitecard ">
                            <div class="dashdescdiv">
                                <h3 class="mb-2">{{ t("Survey Country & Languages") }}</h3>
                                <p class="mb-4">{{ t("Select the country, language or languages in which you plan to run the survey and either select an existing translation of the tool or create your own using a provided template.") }}
                                </p>
                            </div>
                            <div class="dashbuttondiv">
                                @if ($team->languages_progress === 'not_started')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("Not started") }}</span>
                                    </div>
                                @elseif ($team->languages_progress === 'in_progress')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("In Progress") }}</span>
                                    </div>
                                @elseif ($team->languages_progress === 'complete')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("Complete") }}</span>
                                    </div>
                                @endif
                                <a href="{{ \App\Filament\App\Pages\SurveyLanguages\SurveyLanguagesIndex::getUrl() }}" class="buttona">
                                    {{ t("View and Update") }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sampling card -->
                <div class="flex flex-col lg:flex-row drop-shadow-lg overflow-hidden col-span-12 lg:col-span-6 lg:h-72">
                    <!-- Green Section -->
                    <div class=" greensection">
                        <img src="/images/sampling_icon.png" alt="Sampling Icon" class="w-8 mb-2 ml-8 lg:ml-0">
                        <div class="w-3/4 mx-10 lg:w-full lg:mx-0 lg:text-center">
                            <span class="mt-2 text-center">{{ t("Sampling") }}</span>
                            <!-- Progress bar -->
                            @if ($team->sampling_progress === 'complete')
                                <div class="w-3/4 bg-white bg-opacity-50 rounded-full h-2.5 mt-8 lg:mx-auto">
                                    <div class="bg-white h-2.5 rounded-full w-full"></div>
                                </div>
                            @elseif ($team->sampling_progress === 'in_progress')
                                <div class="w-3/4 bg-white bg-opacity-50 rounded-full h-2.5 mt-8 lg:mx-auto">
                                    <div class="bg-white h-2.5 rounded-full w-6/12"></div>
                                </div>
                            @else
                                <div class="w-3/4 bg-white bg-opacity-50 rounded-full h-2.5 mt-8 lg:mx-auto">
                                    <div class="bg-white h-2.5 rounded-full w-1/12"></div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <!-- White Section -->
                    <div class="whitesection">
                        <div class=" whitecard ">
                            <div class="dashdescdiv">
                                <h3 class="mb-2">{{ t("Survey Locations") }}</h3>
                                <p class="mb-4">{{ t("Add the details of the farms you will visit, to allow the enumerators to carry out data collection.") }}</p>
                            </div>
                            <div class="dashbuttondiv">
                                @if ($team->sampling_progress === 'not_started')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("Not started") }}</span>
                                    </div>
                                @elseif ($team->sampling_progress === 'in_progress')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("In Progress") }}</span>
                                    </div>
                                @elseif ($team->sampling_progress === 'complete')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("Complete") }}</span>
                                    </div>
                                @endif
                                <a href="{{ url(SurveyLocationsIndex::getUrl()) }}" class="buttona uppercase">
                                    {{ t("View and Update") }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Localisation card -->
                <div class="flex flex-col lg:flex-row drop-shadow-lg overflow-hidden col-span-12  ">
                    <!-- Green Section -->
                    <div class=" greensection ">
                        <img src="/images/localisation_icon.png" alt="Localisation Icon" class="w-8 mb-2 ml-8 lg:ml-0">
                        <div class="w-3/4 mx-10 lg:w-full lg:mx-0 lg:text-center">
                            <span class="mt-2 text-center">{{ t("Localisation") }}</span>
                            <!-- Progress bar -->
                            @if ($team->pba_progress === 'not_started' && $team->optional_modules_progress === 'not_started' && $team->pilot_progress === 'not_started')
                                <div class="w-3/4 bg-white bg-opacity-50 rounded-full h-2.5 mt-8 lg:mx-auto">
                                    <div class="bg-white h-2.5 rounded-full w-1/12"></div>
                                </div>
                            @elseif ($team->pba_progress === 'complete' && $team->optional_modules_progress === 'complete' && $team->pilot_progress === 'complete')
                                <div class="w-3/4 bg-white bg-opacity-50 rounded-full h-2.5 mt-8 lg:mx-auto">
                                    <div class="bg-white h-2.5 rounded-full w-full"></div>
                                </div>
                            @else
                                <div class="w-3/4 bg-white bg-opacity-50 rounded-full h-2.5 mt-8 lg:mx-auto">
                                    <div class="bg-white h-2.5 rounded-full w-6/12"></div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <!-- White Section -->
                    <div class="whitesection">
                        <div class="whiteborderbox">
                            <div class=" whitecard ">
                                <div class="dashdescdiv">
                                    <h3 class="mb-2">{{ t("Place-based adaptations") }}</h3>
                                    <p class="mb-4">{{ t("Customise details for questions and answer options to ensure the survey is relevant and suitable for use in the intended location.") }}</p>
                                </div>
                                <div class="dashbuttondiv">
                                    @if ($team->pba_progress === 'not_started')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("Not started") }}</span>
                                        </div>
                                    @elseif ($team->pba_progress === 'in_progress')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("In Progress") }}</span>
                                        </div>
                                    @elseif ($team->pba_progress === 'complete')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("Complete") }}</span>
                                        </div>
                                    @endif
                                    <a href="{{ PlaceAdaptationsIndex::getUrl() }}" class="buttona uppercase">
                                        {{ t("View and Update") }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="whitesection">
                        <div class="whiteborderbox">
                            <div class=" whitecard ">
                                <div class="dashdescdiv">
                                    <h3 class="mb-2">{{ t("Localisation: Optional Modules") }}</h3>
                                    <p class="mb-4">{{ t("Add optionals modules to the survey.") }}</p>
                                </div>
                                <div class="dashbuttondiv">
                                    @if ($team->optional_modules_progress === 'not_started')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("Not started") }}</span>
                                        </div>
                                    @elseif ($team->optional_modules_progress === 'in_progress')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("In Progress") }}</span>
                                        </div>
                                    @elseif ($team->optional_modules_progress === 'complete')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("Complete") }}</span>
                                        </div>
                                    @endif
                                    <a href="{{ url(OptionalModules::getUrl()) }}" class="buttona uppercase">
                                        {{ t("View and Update") }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="whitesection">
                        <div class=" whitecard ">
                            <div class="dashdescdiv">
                                <h3 class="mb-2">{{ t("Localisation: Pilot") }}</h3>
                                <p class="mb-4">{{ t("Conduct a pilot run of the survey, both for quality control of the customised survey and training of enumerators.") }}
                                </p>
                            </div>
                            <div class="dashbuttondiv">
                                @if ($team->pilot_progress === 'not_started')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("Not Started") }}</span>
                                    </div>
                                @elseif ($team->pilot_progress === 'complete')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("Complete") }}</span>
                                    </div>
                                @endif
                                <a href="{{ url(PilotIndex::getUrl()) }}" class="buttona uppercase">
                                    {{ t("View and Update") }}
                                </a>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Live Data Collection card -->
                <div class="flex flex-col lg:flex-row drop-shadow-lg overflow-hidden col-span-12">
                    <!-- Green Section -->
                    <div class=" greensection">
                        <img src="/images/data_collection_icon.png" alt="Live Data Collection Icon" class="w-8 mb-2 ml-8 lg:ml-0">
                        <div class="w-3/4 mx-10 lg:w-full lg:mx-0 lg:text-center">
                            <span class="mt-2 text-center">{{ t("Live Data Collection") }}</span>
                            <!-- Progress bar -->
                            @if ($team->setup_survey_progress === 'not_started' && $team->data_collection_progress === 'not_started')
                                <div class="w-3/4 bg-white bg-opacity-50 rounded-full h-2.5 mt-8 lg:mx-auto">
                                    <div class="bg-white h-2.5 rounded-full w-1/12"></div>
                                </div>
                            @elseif ($team->setup_survey_progress === 'complete' && $team->data_collection_progress === 'complete')
                                <div class="w-3/4 bg-white bg-opacity-50 rounded-full h-2.5 mt-8 lg:mx-auto">
                                    <div class="bg-white h-2.5 rounded-full w-full"></div>
                                </div>
                            @else
                                <div class="w-3/4 bg-white bg-opacity-50 rounded-full h-2.5 mt-8 lg:mx-auto">
                                    <div class="bg-white h-2.5 rounded-full w-6/12"></div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <!-- White Section: Set up the survey -->
                    <div class="whitesection">
                        <div class="whiteborderbox">
                            <div class=" whitecard ">
                                <div class="dashdescdiv">
                                    <h3 class="mb-2">{{ t("Set up the survey") }}</h3>
                                    <p class="mb-4">{{ t("Manage the ODK survey to be used for data collection") }}</p>
                                </div>
                                <div class="dashbuttondiv">
                                    @if ($team->setup_survey_progress === 'not_started')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("Not started") }}</span>
                                        </div>
                                    @elseif ($team->setup_survey_progress === 'complete')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("Complete") }}</span>
                                        </div>
                                    @endif
                                    <a href="{{ SetUpSurvey::getUrl() }}" class="buttona uppercase">
                                        {{ t("View and Update") }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- White Section: Monitor Data Collection -->
                    <div class="whitesection">
                        <div class="whiteborderbox">
                            <div class=" whitecard ">
                                <div class="dashdescdiv">
                                    <h3 class="mb-2">{{ t("Monitor Data Collection") }}</h3>
                                    <p class="mb-4">{{ t("Review and quality-check incoming data") }}</p>
                                </div>
                                <div class="dashbuttondiv">
                                    @if ($team->data_collection_progress === 'not_started')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("Not started") }}</span>
                                        </div>
                                    @elseif ($team->data_collection_progress === 'in_progress')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("In Progress") }}</span>
                                        </div>
                                    @elseif ($team->data_collection_progress === 'complete')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs uppercase font-semibold">{{ t("Complete") }}</span>
                                        </div>
                                    @endif
                                    <a href="{{ MonitorDataCollection::getUrl() }}" class="buttona uppercase">
                                        {{ t("View and Update") }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- White Section: Data Analysis & Results -->
                    <div class="whitesection">
                        <div class=" whitecard ">
                            <div class="dashdescdiv">
                                <h3 class="mb-2">{{ t("Data Analysis & Results") }}</h3>
                                <p class="mb-4">{{ t("Download data to conduct data analysis.") }}</p>
                            </div>
                            <div class="dashbuttondiv">
                                <a href="{{ DataAnalysisIndex::getUrl() }}" class="buttona uppercase">
                                    {{ t("View Data") }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-filament-panels::page>
