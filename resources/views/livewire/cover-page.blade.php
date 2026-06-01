<div class="explore-page">

    <!-- Hero: background image with overlay -->
    <div class="relative h-[82vh]">

        <img src="/images/groundswell-international-bg.jpg" alt="Background" class="absolute inset-0 w-full h-full object-cover object-center">
        <div class="absolute inset-0 bg-black/40 z-10 flex flex-col">

            <!-- Top bar: logo + nav -->
            <div class="flex items-center justify-between px-8 sm:px-12 lg:px-16 py-8">
                <img src="/images/transparent_logo.webp" alt="Groundswell International" class="h-10 w-auto">
                <div class="flex items-center gap-4">
                    <div class="relative inline-block text-left group">
                        <button type="button" class="text-white text-sm font-bold uppercase tracking-wide transition">
                            {{ t("Language") }} <span class="text-xs ml-0.5">▾</span>
                        </button>
                        <div class="absolute right-0 top-full hidden group-hover:block pt-2 z-50">
                            <div class="bg-white rounded-xl shadow-xl overflow-hidden min-w-[90px]">
                                <a class="block px-4 py-3 text-brown text-sm hover:bg-gray-50 hover:text-green transition font-medium" href="{{ URL::current() . '?locale=en' }}">English</a>
                                <a class="block px-4 py-3 text-brown text-sm hover:bg-gray-50 hover:text-green transition font-medium" href="{{ URL::current() . '?locale=es' }}">Español</a>
                                <a class="block px-4 py-3 text-brown text-sm hover:bg-gray-50 hover:text-green transition font-medium" href="{{ URL::current() . '?locale=fr' }}">Français</a>
                            </div>
                        </div>
                    </div>
                    <a href="{{ url('app') }}" class="border border-white/40 rounded-full px-5 py-2 text-white text-sm font-bold hover:bg-white hover:text-green transition uppercase tracking-wide">
                        {{ t("Log in") }}
                    </a>
                </div>
            </div>

            <!-- Centered title -->
            <div class="flex-1 flex items-center justify-center text-center px-6">
                <div>
                    <h1 class="!text-white text-5xl sm:text-6xl lg:text-7xl font-extrabold leading-tight tracking-tight mb-4">
                        {{ t("Survey Tool") }}
                    </h1>
                    <p class="text-white/65 text-base max-w-sm mx-auto leading-relaxed">
                        {{ t("Groundswell International's online platform for managing survey data collection.") }}
                    </p>
                </div>
            </div>

            <!-- Three cards -->
            <div class="px-8 sm:px-12 lg:px-16 pb-8">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                    <a href="{{ url('#surveys') }}" class="group rounded-2xl p-6 flex flex-col hover:-translate-y-1 transition-all duration-300" style="background-color: rgba(196, 93, 93, 0.82); backdrop-filter: blur(4px);">
                        <h3 class="!text-white !font-bold !text-xl mb-2 uppercase tracking-wide">{{ t("Surveys") }}</h3>
                        <p class="text-white/70 text-sm flex-1 leading-relaxed">{{ t("Read more about the surveys.") }}</p>
                        <div class="flex items-center gap-2 mt-5 text-white text-sm font-semibold">
                            {{ t("Find out more") }}
                            <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </div>
                    </a>

                    <a href="{{ url('#online-tool') }}" class="group rounded-2xl p-6 flex flex-col hover:-translate-y-1 transition-all duration-300" style="background-color: rgba(94, 150, 145, 0.82); backdrop-filter: blur(4px);">
                        <h3 class="!text-white !font-bold !text-xl mb-2 uppercase tracking-wide">{{ t("Online Tool") }}</h3>
                        <p class="text-white/70 text-sm flex-1 leading-relaxed">{{ t("An online interface to help you tailor the surveys to your context and conduct a survey.") }}</p>
                        <div class="flex items-center gap-2 mt-5 text-white text-sm font-semibold">
                            {{ t("Read more") }}
                            <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </div>
                    </a>

                    <a href="{{ url('#results') }}" class="group rounded-2xl p-6 flex flex-col hover:-translate-y-1 transition-all duration-300" style="background-color: rgba(243, 159, 93, 0.82); backdrop-filter: blur(4px);">
                        <h3 class="!text-white !font-bold !text-xl mb-2 uppercase tracking-wide">{{ t("Results") }}</h3>
                        <p class="text-white/70 text-sm flex-1 leading-relaxed">{{ t("Results from previous implementations.") }}</p>
                        <div class="flex items-center gap-2 mt-5 text-white text-sm font-semibold">
                            {{ t("Learn more") }}
                            <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </div>
                    </a>

                </div>
            </div>

        </div>
    </div>

    {{-- MAIN CONTENT --}}
    <div style="background-color:#FDFBFB;">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">

            <div id="surveys" class="py-6 grid grid-cols-1 lg:grid-cols-2 gap-5 items-center mb-12">
                <div class="rounded-2xl h-96 shadow-sm" style="background-image:url('/images/farmers_honduras.jpg'); background-position:center; background-size:cover;"></div>
                <div>
                    <h3 class="!text-brown !text-3xl !font-extrabold mb-6 leading-tight">{{ t("Surveys") }}</h3>
                    <p class="text-brown/70 leading-relaxed text-sm">{{ t("Groundswell International develops and implements surveys to support data collection, learning, monitoring, and evaluation across a range of programmes and contexts. Surveys can be adapted to different projects, stakeholder groups, and implementation settings, enabling teams to collect meaningful and context-specific information to support decision-making, reporting, and programme improvement. The survey approach supports flexible digital data collection, customised indicators, and streamlined analysis workflows, helping teams manage and interpret data efficiently across multiple locations and activities.") }}</p>
                </div>
            </div>

            <div id="online-tool" class="py-6 grid grid-cols-1 lg:grid-cols-2 gap-5 items-center mb-12">
                <div class="order-2 lg:order-1">
                    <h3 class="!text-brown !text-3xl !font-extrabold mb-6 leading-tight">{{ t("Online Tool") }}</h3>
                    <p class="text-brown/70 leading-relaxed text-sm">
                        {{ t("Teams wishing to implement Groundswell International surveys can request access to the online platform designed to support the setup, customisation, deployment, and management of surveys across different contexts.") }}
                        <br><br>
                        {{ t("The platform guides users through the process of adapting survey modules, configuring indicators, implementing data collection activities, and accessing results and reporting features.") }}
                        <br><br>
                        {{ t("For more information, or to register your interest in using the platform, please use the links below. Existing users can log in using their account credentials.") }}
                    </p>
                    <div class="mt-10 flex flex-row gap-4 flex-wrap">
                        <a href="{{ url('app') }}" class="button bg-blue hover:bg-white border-2 border-blue rounded-full px-4 py-2 text-white hover:text-blue uppercase font-semibold w-auto flex justify-center items-center text-center">{{ t("Log in") }}</a>
                        {{ $this->registerInterestAction }}
                    </div>
                </div>
                <div class="rounded-2xl h-96 shadow-sm order-1 lg:order-2" style="background-image:url('/images/seeds.jpg'); background-position:center; background-size:cover;"></div>
            </div>

            <div id="results" class="py-6 grid grid-cols-1 lg:grid-cols-2 gap-5 items-center mb-12">
                <div class="rounded-2xl h-96 shadow-sm" style="background-image:url('/images/farmer_haiti.webp'); background-position:center; background-size:cover;"></div>
                <div>
                    <h3 class="!text-brown !text-3xl !font-extrabold mb-6 leading-tight">{{ t("Results") }}</h3>
                    <p class="text-brown/70 leading-relaxed text-sm mb-10">{{ t("The tool has been applied across multiple countries and programme contexts. Results dashboards and country reports enable users to explore findings, compare indicators, and visualise trends across implementations.") }}</p>
                    <a href="{{ url('results') }}" class="button bg-blue hover:bg-white border-2 border-blue rounded-full px-4 py-2 text-white hover:text-blue uppercase font-semibold inline-flex items-center">
                        {{ t("View Dashboard") }}
                    </a>
                </div>
            </div>

        </div>
    </div>

    <x-filament-actions::modals/>

</div>
