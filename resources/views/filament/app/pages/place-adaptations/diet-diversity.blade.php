@php $instructions = tfile('place-adaptations-diet-diversity'); @endphp

<x-filament-panels::page>
    <x-instructions-sidebar>
        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>
    </x-instructions-sidebar>

    <div class="container mx-auto  ">
        <div class="surveyblocks pt-16 pb-24 mb-32 px-12 lg:px-16">
            <p class="mb-4">{{ t("HOLPA uses an internationally validated indicator for \"dietary diversity\". This indicator has a pre-defined set of questions, which is different for different countries. More information about this module can be found on the") }}
                <a href="https://www.dietquality.org/tools" class="text-green font-semibold">Global Diet Quality Project</a> {{ t("website.") }}
            </p>
            <p class="mb-6">{{ t("To complete this section of the localisation, please select the version of the questions that you would like to use for your survey. If an option for your country does not exist, you can either use the default version (without locally-relevant food examples), or contact the HOLPA team to ask for your country's module to be added.") }}</p>
            <p class="mb-6">{{ t("It is assumed that you are conducting HOLPA within one country. If you are working across multiple countries, we recommend creating a different \"team\" for each country so that you can make different customisations to the survey within the different countries.") }}</p>

            {{ $this->form }}
            <div class="h-12"></div>

            <p class="mb-6">{{ t("Below are the questions that form this module. When you select your country, this table will update to show the text that will appear in your version of the survey.") }}</p>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
