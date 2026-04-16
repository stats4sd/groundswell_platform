<x-filament-panels::page>

    <x-instructions-sidebar>
        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>

            <div class="mx-12 mb-4">
                <h5>Add Context Questions</h5>
                <p class="mb-2">
                    The HOLPA Survey already contains a set of core 'context' questions: questions asked about the farm household itself, to help put the agroecology and performance indicators into context. These include questions on the household roster, average education level of members, specifics about the respondent (to help identify possible biases) and whether household members have received specific types of training within the previous year. These questions are asked at the beginning of the household survey.
                </p>
                <p class="mb-2">
                    If your project requires it, you may add a small number of additional questions to this section. For example, you may wish to ask whether the household has participated in specific events run by your intervention project, or ask about some specific landscape properties on the farm. To do so, add the questions to this page using the form provided. These questions will be included into your household form when it is next deployed.
                </p>
            </div>

        </x-slot:instructions>
    </x-instructions-sidebar>


{{ $this->table }}



</x-filament-panels::page>
