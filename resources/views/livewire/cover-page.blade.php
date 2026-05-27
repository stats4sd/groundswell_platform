<div class="explore-page">


    <!-- Logo for small/medium screens - centered above title -->


    <div class="relative h-screen lg:h-auto">
        <!-- Background Image (absolutely positioned to go as a backdrop) -->
        <img src="images/groundswell-international-bg.jpg" alt="Background Image" class="w-full h-full md:max-h-[80vh] lg:max-h-[70vh] object-cover absolute" style="object-position:center; z-index: 0">

        <!-- Overlay Content -->
        <div class="relative inset-0 flex  text-center items-center justify-center h-full md:max-h-[80vh] lg:max-h-[70vh] bg-black bg-opacity-50 pt-20 lg:pt-2 z-10">
            <div class="w-max flex flex-col items-left relative lg:top-16">
                
            <!-- Headings -->
                <div class="relative flex items-center mb-0 text-center lg:text-left px-4 pt-20 lg:pt-36">

                    <div class="flex flex-col lg:flex-row space-y-6 lg:space-y-0 lg:space-x-8 w-full items-center lg:items-stretch mt-10 lg:mt-16 pb-8">

                        <!-- Left Content -->
                        <div class="flex-grow max-w-3xl">

                            <h2 class="text-white text-5xl mb-2 lg:mb-4">
                                {{ t("Groundswell International") }}
                            </h2>

                            <h3 class="text-white text-2xl sm:text-3xl lg:text-5xl mb-2 lg:mb-4">
                                {{ t("Survey Tool") }}
                            </h3>

                        </div>

                        <!-- Right Buttons -->
                        <div class="content-end flex justify-end flex-grow">

                            <div class="flex flex-col gap-4 items-center lg:items-end">

                                <!-- Language Switcher -->
                                <div class="relative inline-block text-left group">

                                    <!-- Button -->
                                    <button
                                        type="button"
                                        class="bg-transparent border-2 border-white rounded-full px-6 py-2 text-white text-base font-semibold leading-none hover:bg-white hover:text-black transition"
                                    >
                                        {{ t("Change Language") }}
                                    </button>

                                    <!-- Dropdown Menu -->
                                    <div
                                        class="absolute right-0 top-full hidden group-hover:block bg-white rounded-lg shadow-lg overflow-hidden min-w-[180px] z-50"
                                    >

                                        <a
                                            class="block px-4 py-3 text-black text-base hover:bg-gray-100 transition"
                                            href="{{ URL::current() . '?locale=en' }}"
                                        >
                                            English
                                        </a>

                                        <a
                                            class="block px-4 py-3 text-black text-base hover:bg-gray-100 transition"
                                            href="{{ URL::current() . '?locale=es' }}"
                                        >
                                            Español
                                        </a>

                                        <a
                                            class="block px-4 py-3 text-black text-base hover:bg-gray-100 transition"
                                            href="{{ URL::current() . '?locale=fr' }}"
                                        >
                                            Français
                                        </a>

                                    </div>

                                </div>

                                <!-- Login Button -->
                                <a 
                                    href="{{ url('app") }}" 
                                    class="border-2 rounded-full px-6 py-2 text-white uppercase text-base font-semibold leading-none hover:bg-white hover:text-black transition text-center"
                                >
                                    {{ t("Log in") }}
                                </a>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- Cards -->

                <div class="flex flex-col lg:flex-row justify-center space-y-6 lg:space-y-0 lg:space-x-8 w-full items-center lg:items-stretch px-4 mt-10 lg:mt-8 pb-8">
                    <!-- Surveys Card -->
                    <div class="explore-card bg-orange flex flex-col items-center">
                        <img src="images/themes_line.png" alt="Icon 1" class="absolute top-[15px] right-[15px] h-8 w-8 lg:h-8 lg:w-8">
                        <div class="flex-1 text-center">
                            <h3 class="text-white uppercase text-lg lg:text-xl mb-4 lg:my-4">{{ t("Surveys") }}</h3>
                            <p class="mb-4 lg:mb-6">{{ t("Read more about the surveys.") }}</p>
                        </div>
                        <a href="{{ url('#what-is-holpa") }}" class="button bg-orange hover:bg-white uppercase b-white border-2 rounded-full  px-4 py-2 text-white hover:text-orange font-semibold w-auto flex justify-center items-center text-center">
                            {{ t("Find out more") }}
                        </a>
                    </div>

                    <!-- Online Tool Card -->
                    <div class="explore-card bg-green flex flex-col items-center">
                        <img src="images/tools_line.png" alt="Icon 2" class="absolute top-[15px] right-[15px] h-8 w-8 lg:h-8 lg:w-8">
                        <div class="flex-1 text-center ">
                            <h3 class=" text-white uppercase text-lg lg:text-xl mb-4 lg:my-4">{{ t("Online Tool") }}</h3>
                            <p class="mb-4 lg:mb-6">{{ t("An online interface to help you tailor the surveys to your context and conduct a survey.") }}</p>
                        </div>
                        <a href="{{ url('#online-tool") }}" class="button bg-green hover:bg-white uppercase b-white border-2 rounded-full  px-4 py-2 text-white hover:text-green font-semibold w-auto flex justify-center items-center text-center">
                            {{ t("Read more") }}
                        </a>
                    </div>

                    <!-- Results Card -->
                    <div class="explore-card bg-blue flex flex-col items-center">
                        <img src="images/metrics_line.png" alt="Icon 3" class="absolute top-[15px] right-[15px] h-8 w-8 lg:h-8 lg:w-8">
                        <div class="flex-1 text-center">
                            <h3 class="text-white uppercase text-lg lg:text-xl mb-4 lg:my-4">{{ t("Results") }}</h3>
                            <p class="mb-4 lg:mb-6">{{ t("Results from previous implementations.") }}</p>
                        </div>
                        <a href="{{ url('#results") }}" class="button bg-blue hover:bg-white b-white border-2 rounded-full px-4 py-2 text-white uppercase hover:text-blue font-semibold w-auto flex justify-center items-center text-center mb-4">
                            {{ t("Learn More") }}
                        </a>
                        <a href="{{ url('results") }}" class="button bg-blue hover:bg-white b-white border-2 rounded-full  px-4 py-2 text-white uppercase hover:text-blue font-semibold w-auto flex justify-center items-center text-center">
                            {{ t("Previous Surveys") }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN CONTENT --}}
    <div class="mx-auto mt-28 md:mt-0 lg:mt-44 max-w-7xl px-4 sm:px-6 lg:px-8">

        <div id="what-is-holpa" class="py-6 lg:grid flex flex-col lg:grid-cols-2 gap-5  mb-12">
            <div class="col-span-1 h-96 mx-12 mb-6 lg:mr-6 lg:ml-0 lg:mb-0 rounded-2xl" style="background-image:url('images/farmers_honduras.jpg');  background-position:center; background-size: cover;" alt="Picture of farmers">

            </div>

            <div class="col-span-1 flex-col px-16 place-content-center">
                <h3 class="text-3xl mb-8">{{ t("Surveys") }}</h3>
                <p>
                    {{ t("Groundswell International develops and implements surveys to support data collection, learning, monitoring, and evaluation across a range of programmes and contexts. Surveys can be adapted to different projects, stakeholder groups, and implementation settings, enabling teams to collect meaningful and context-specific information to support decision-making, reporting, and programme improvement. The survey approach supports flexible digital data collection, customised indicators, and streamlined analysis workflows, helping teams manage and interpret data efficiently across multiple locations and activities.") }}
                </p>
            </div>
        </div>

        <div id="online-tool" class="py-6 lg:grid flex flex-col-reverse lg:grid-cols-2 gap-5  mb-12">


            <div class="col-span-1 flex-col px-16 place-content-center">
                <h3 class="text-3xl mb-8">{{ t("Online Tool") }}</h3>
                <p>
                    {{ t("Teams wishing to implement Groundswell International surveys can request access to the online platform designed to support the setup, customisation, deployment, and management of surveys across different contexts.") }}
                    <br><br>
                    {{ t("The platform guides users through the process of adapting survey modules, configuring indicators, implementing data collection activities, and accessing results and reporting features.") }}
                    <br><br>
                    {{ t("For more information, or to register your interest in using the platform, please use the links below. Existing users can log in using their account credentials.") }}
                </p>
                <div class="mt-8 place-content-center flex flex-row w-full">
                    <a href="{{ url('app") }} " class="button uppercasere bg-blue hover:bg-white b-white border-2 rounded-full px-4 py-2 text-white hover:text-blue font-semibold w-auto flex justify-center items-center text-center mx-2">{{ t("Log in") }}</a>
                    {{ $this->registerInterestAction }}
                </div>
            </div>
            <div class="col-span-1 h-96 mx-12 mb-6 lg:mr-6 lg:ml-0 lg:mb-0 rounded-2xl" style="background-image:url('images/seeds.jpg');  background-position:center; background-size: cover;" alt="Picture of hands holding seeds">

            </div>
        </div>


        <div id="results" class="py-6 lg:grid flex flex-col lg:grid-cols-2 gap-5  mb-12">
            <div class="col-span-1 rounded-2xl h-96 mx-12 mb-6 lg:mr-6 lg:ml-0 lg:mb-0" style="background-image:url('images/farmer_haiti.webp');  background-position:center; background-size: cover;" alt="Picture of a farmer">


            </div>

            <div class="col-span-1  flex-col px-16 place-content-center">
                <h3 class="text-3xl mb-8">{{ t("Results") }}</h3>
                <p class="mb-8">{{ t("The tool has been applied across multiple countries and programme contexts. Results dashboards and country reports enable users to explore findings, compare indicators, and visualise trends across implementations.") }}</p>
                <a href="{{ url('results") }}" class="button bg-blue hover:bg-white b-white border-2 rounded-full px-4 py-2 text-white hover:text-blue uppercase font-semibold w-auto flex justify-center items-center text-center">
                    {{ t("View Dashboard") }}
                </a>
                </p>
            </div>
        </div>

    </div>

    <x-filament-actions::modals/>

</div>
