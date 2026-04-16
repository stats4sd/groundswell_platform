<x-filament-panels::page>
    <x-instructions-sidebar>
        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>
            <div class="mx-12 mb-4">

                <h5>Diet Diversity module </h5>

                <p class="mb-2">
                    HOLPA uses an internationally validated indicator for "dietary diversity". The questions in this section ask whether members of the household have consumed anything from specific food groups within the last 24 hours, such as grain food, tubers, pulses, green veg, etc. The default survey has all the needed questions, but does not include lists of locally contextualised example foods for each group.
                </p>
                <p class="mb-2">
                    The platform can incorporate localised versions of the questions from the
                    <a href="https://www.dietquality.org/tools" class="text-green font-semibold" target="_blank"
                    >Global Diet Quality Project</a>, which add relevant example foods for each category customised for over 100 countries. If you would like to include these in your survey, select the suitable country from the list of available countries. The page shows the questions that will appear in the survey, so you can review the default and the localised versions with examples, and decide what to use for your survey.
                </p>
            </div>
        </x-slot:instructions>
    </x-instructions-sidebar>

    <div class="container mx-auto  ">
        <div class="surveyblocks pt-16 pb-24 mb-32 px-12 lg:px-16">
            <p class="mb-4">HOLPA uses an internationally validated indicator for "dietary diversity". This indicator has a pre-defined set of questions, which is different for different countries. More information about this module can be found on the
                <a href="https://www.dietquality.org/tools" class="text-green font-semibold">Global Diet Quality Project</a> website.
            </p>
            <p class="mb-6">To complete this section of the localisation, please select the version of the questions that you would like to use for your survey. If an option for your country does not exist, you can either use the default version (without locally-relevant food examples), or contact the HOLPA team to ask for your country's module to be added.</p>
            <p class="mb-6">It is assumed that you are conducting HOLPA within one country. If you are working across multiple countries, we recommend creating a different "team" for each country so that you can make different customisations to the survey within the different countries.</p>

            {{ $this->form }}
            <div class="h-12"></div>

            <p class="mb-6">Below are the questions that form this module. When you select your country, this table will update to show the text that will appear in your version of the survey.</p>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
