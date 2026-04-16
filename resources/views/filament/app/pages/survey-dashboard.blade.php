<?php

use App\Filament\App\Pages\DataAnalysis\DataAnalysisIndex;
use App\Filament\App\Pages\Lisp\LispIndex;
use App\Filament\App\Pages\Pilot\PilotIndex;
use App\Filament\App\Pages\PlaceAdaptations\PlaceAdaptationsIndex;
use App\Filament\App\Pages\SurveyLocations\SurveyLocationsIndex;

?>

<x-filament-panels::page>

    <x-instructions-sidebar :videoUrl="'#'">
        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>

            {{-- <div class="pr-4 content-center  mx-auto my-4">
                <iframe class="rounded-3xl" src="https://www.youtube.com/embed/TODO_ADD_VIDEO_ID" style="width: 560px; height: 315px;" frameborder="0" allowfullscreen></iframe>
            </div> --}}
            <div class="mx-12 mb-4">
                <p class="mb-2">
                    This is the HOLPA survey builder dashboard. Here you can see an overview of the tasks required to prepare and deliver the survey, and you can keep track of your progress.</p>
                <!-- <a class="buttona px-auto" href="{{ url('/') }}">Find out more about HOLPA here</a><br/><br/> -->
                <h5>Do I need to complete the sections in order? </h5>
                <p class="mb-2">
                    The sections do not have to be completed in order. Although they are in a logical order, most users will probably go back and forth and revisit sections. You can mark sections as complete to keep track of your work, but you can still make changes in a "completed" section.</p>
                <h5> How do I complete a section? </h5>
                <p class="mb-2">
                    Within each section, there are usually multiple actions. Most actions will have options to add information or adjust elements of your survey. Some actions are prompts for tasks that take place outside of the tool, such as the local indicator selection workshop. When you are finished with a section, mark it as complete using the button at the bottom of the screen; this will help you and your team keep track of your progress.
                </p>
                <p class="mb-2">
                    Each section also has instructions in text and video form.
                </p>

                <h5 class="mb-2">What's on the dashboard?</h5>
                <p>
                The dashboard contains sections for each of the different tasks that need to be done to prepare and implement HOLPA. They are sorted into headings for different aspects of the process:
                </p>
                <ul class="instructions_list list-disc ">
                    <li>
                        <span class="font-semibold">Prepare survey</span><br>
                        This is where you indicate the country and language (or languages) in which you will be preparing the survey, and select or provide the translated versions of HOLPA to be used.
                        This will generate the forms which you will be customising and using throughout the rest of the process. You will not be able to complete some other steps until you have selected a country, language and translation.
                    </li>
                    <li>
                        <span class="font-semibold">Survey locations</span><br>
                        Here, you will provide the details of the farms/locations to be visited. This is necessary to allow enumerators to conduct the survey; and possibly for data analysis later on.
                    </li>
                    <li>
                        <span class="font-semibold">Localisation</span><br>
                        The localisation process is a crucial aspect of the implementation of HOLPA. The HOLPA tool aims to balance harmonisation and comparability between results with specific adaptations to ensure those results are applicable and useful at a local level. The localisation sections allow you to adjust the HOLPA survey to ensure it is relevant to the target audience.

                        These sections are:
                        <ul class="ml-6 " style="  list-style-type: circle;">
                            <li>
                                'Place based adaptations', which allows you to adjust details of the survey such as a suitable time frame to ask about recent events, and the specific foods, crops, animals and other units which might be asked about, so that the answer options make sense in the context. At the end of this section, you are prompted to conduct an initial pilot to check the sense and functionality of the survey.
                            </li>
                            <li>
                                The local indicator selection process (LISP), which involves holding a workshop with local farmers and stakeholders to identify a set of contextually-specific indicators to include in the HOLPA tool. You can then add those local indicators into the customised HOLPA tool.
                            </li>
                            <li>
                                A full pilot test of the customised HOLPA survey, allowing for quality control and for training of enumerators. After completing these activities, it may be necessary to return to previous sections and make changes to your survey.
                            </li>
                        </ul>


                    </li>
                    <li>
                        <span class="font-semibold">Data collection</span><br>

                        When you commence data collection for your survey, you can monitor, review and edit submissions here.
                    </li>
                    <li>
                        <span class="font-semibold">Download data </span><br>

                        Quickly retrieve all the data collected for your customised HOLPA survey. From there, you may conduct your own analysis or whatever else you wish to do.
                    </li>
                </ul>
            </div>
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
                            <span class="mt-2 text-center">Prepare survey</span>
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
                                <h3 class="mb-2">Survey Country and Languages</h3>
                                <p class="text-gray-600 mb-4">Select the country, language or languages in which you plan to run the survey and either select an existing translation of the tool or create your own using a provided template.
                                </p>
                            </div>
                            <div class="dashbuttondiv">
                                @if ($team->languages_progress === 'not_started')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs font-semibold">NOT STARTED</span>
                                    </div>
                                @elseif ($team->languages_progress === 'in_progress')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs font-semibold">IN PROGRESS</span>
                                    </div>
                                @elseif ($team->languages_progress === 'complete')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs font-semibold">COMPLETE</span>
                                    </div>
                                @endif
                                <a href="{{ \App\Filament\App\Pages\SurveyLanguages\SurveyLanguagesIndex::getUrl() }}" class="buttona">
                                    VIEW AND UPDATE
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
                            <span class="mt-2 text-center">Sampling</span>
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
                                <h3 class="mb-2">Survey Locations</h3>
                                <p class="text-gray-600 mb-4">Add the details of the farms you will visit, to allow the enumerators to carry out data collection.</p>
                            </div>
                            <div class="dashbuttondiv">
                                @if ($team->sampling_progress === 'not_started')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs font-semibold">NOT STARTED</span>
                                    </div>
                                @elseif ($team->sampling_progress === 'in_progress')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs font-semibold">IN PROGRESS</span>
                                    </div>
                                @elseif ($team->sampling_progress === 'complete')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs font-semibold">COMPLETE</span>
                                    </div>
                                @endif
                                <a href="{{ url(SurveyLocationsIndex::getUrl()) }}" class="buttona">
                                    VIEW AND UPDATE
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
                            <span class="mt-2 text-center">Localisation</span>
                            <!-- Progress bar -->
                            @if ($team->pba_progress === 'not_started' && $team->lisp_progress === 'not_started' && $team->pilot_progress === 'not_started')
                                <div class="w-3/4 bg-white bg-opacity-50 rounded-full h-2.5 mt-8 lg:mx-auto">
                                    <div class="bg-white h-2.5 rounded-full w-1/12"></div>
                                </div>
                            @elseif ($team->pba_progress === 'complete' && $team->lisp_progress === 'complete' && $team->pilot_progress === 'complete')
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
                                    <h3 class="mb-2">Place-based adaptations</h3>
                                    <p class="text-gray-600 mb-4">Customise details for questions and answer options to ensure the survey is relevant and suitable for use in the intended location.</p>
                                </div>
                                <div class="dashbuttondiv">
                                    @if ($team->pba_progress === 'not_started')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs font-semibold">NOT STARTED</span>
                                        </div>
                                    @elseif ($team->pba_progress === 'in_progress')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs font-semibold">IN PROGRESS</span>
                                        </div>
                                    @elseif ($team->pba_progress === 'complete')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs font-semibold">COMPLETE</span>
                                        </div>
                                    @endif
                                    <a href="{{ PlaceAdaptationsIndex::getUrl() }}" class="buttona">
                                        VIEW AND UPDATE
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="whitesection">
                        <div class="whiteborderbox">
                            <div class=" whitecard ">
                                <div class="dashdescdiv">
                                    <h3 class="mb-2">Localisation: LISP</h3>
                                    <p class="text-gray-600 mb-4">The local indicator selection process (LISP) involves conducting a workshop with local farmers and stakeholders to brainstorm and prioritise a set of local indicators to include in the HOLPA tool.</p>
                                </div>
                                <div class="dashbuttondiv">
                                    @if ($team->lisp_progress === 'not_started')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs font-semibold">NOT STARTED</span>
                                        </div>
                                    @elseif ($team->lisp_progress === 'in_progress')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs font-semibold">IN PROGRESS</span>
                                        </div>
                                    @elseif ($team->lisp_progress === 'complete')
                                        <div class="mb-6">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                            </svg>
                                            <span class="ml-1 inline text-xs font-semibold">COMPLETE</span>
                                        </div>
                                    @endif
                                    <a href="{{ url(LispIndex::getUrl()) }}" class="buttona">
                                        VIEW AND UPDATE
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="whitesection">
                        <div class=" whitecard ">
                            <div class="dashdescdiv">
                                <h3 class="mb-2">Localisation: Pilot</h3>
                                <p class="text-gray-600 mb-4">Conduct a pilot run of the survey, both for quality control of the customised HOLPA survey and training of enumerators.
                                </p>
                            </div>
                            <div class="dashbuttondiv">
                                @if ($team->pilot_progress === 'not_started')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs font-semibold">NOT STARTED</span>
                                    </div>
                                @elseif ($team->pilot_progress === 'complete')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs font-semibold">COMPLETE</span>
                                    </div>
                                @endif
                                <a href="{{ url(PilotIndex::getUrl()) }}" class="buttona">
                                    VIEW AND UPDATE
                                </a>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Data collection card -->
                <div class="flex flex-col lg:flex-row drop-shadow-lg overflow-hidden col-span-12 lg:col-span-6 lg:h-72">
                    <!-- Green Section -->
                    <div class=" greensection">
                        <img src="/images/data_collection_icon.png" alt="Data Collection Icon" class="w-8 mb-2 ml-8 lg:ml-0">
                        <div class="w-3/4 mx-10 lg:w-full lg:mx-0 lg:text-center">
                            <span class="mt-2 text-center">Data Collection</span>
                            <!-- Progress bar -->
                            @if ($team->data_collection_progress === 'complete')
                                <div class="w-3/4 bg-white bg-opacity-50 rounded-full h-2.5 mt-8 lg:mx-auto">
                                    <div class="bg-white h-2.5 rounded-full w-full"></div>
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
                                <h3 class="mb-2">Data collection</h3>
                                <p class="text-gray-600 mb-4">View and manage the survey and incoming data.</p>
                            </div>
                            <div class="dashbuttondiv">
                                @if ($team->data_collection_progress === 'not_started')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs font-semibold">NOT STARTED</span>
                                    </div>
                                @elseif ($team->data_collection_progress === 'in_progress')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currekntColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs font-semibold">IN PROGRESS</span>
                                    </div>
                                @elseif ($team->data_collection_progress === 'complete')
                                    <div class="mb-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                        </svg>
                                        <span class="ml-1 inline text-xs font-semibold">COMPLETE</span>
                                    </div>
                                @endif
                                <a href="{{ \App\Filament\App\Pages\DataCollection\DataCollectionIndex::getUrl() }}" class="buttona">
                                    VIEW AND UPDATE
                                </a>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Data analysis card -->
                <div class="flex flex-col lg:flex-row drop-shadow-lg overflow-hidden col-span-12 lg:col-span-6 lg:h-72">
                    <!-- Green Section -->
                    <div class=" greensection">
                        <img src="/images/data_analysis_icon.png" alt="Data Aanalysis Icon" class="w-8 mb-2 ml-8 lg:ml-0">
                        <div class="w-3/4 mx-10 lg:w-full lg:mx-0 lg:text-center">
                            <span class="mt-2 text-center">Datasets</span>
                        </div>
                    </div>
                    <!-- White Section -->
                    <div class="whitesection">
                        <div class=" whitecard ">
                            <div class="dashdescdiv">
                                <h3 class="mb-2">Download data</h3>
                                <p class="text-gray-600 mb-4">Download data to conduct data analysis.</p>
                            </div>
                            <div class="dashbuttondiv">
                                <a href="{{ DataAnalysisIndex::getUrl() }}" class="buttona">
                                    VIEW DATA
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-filament-panels::page>
