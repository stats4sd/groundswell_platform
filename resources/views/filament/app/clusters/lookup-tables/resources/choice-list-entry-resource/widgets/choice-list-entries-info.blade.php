
<x-filament-widgets::widget>
<x-instructions-sidebar>
        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>

            {{-- <div class="pr-4 content-center  mx-auto my-4">
                <iframe class="rounded-3xl" src="https://www.youtube.com/embed/TODO_ADD_VIDEO_ID" style="width: 560px; height: 315px;" frameborder="0" allowfullscreen></iframe>
            </div> --}}
            <div class="mx-12 mb-4">
      

                <p class="mb-2">
                    There are some questions in the survey where the appropriate answer options will be different depending on the location context - for example, questions that ask about crops that are grown on a farm
                    <i>should not</i> include lots of options for plants that do not grow in the location being surveyed, and
                    <i>should</i> include the most commonly grown crops in that area. Questions should also reflect the units of measurement that are used in the location.
                </p>
                <p class="mb-2">
                    The "Contextualise choice lists" has several choice lists to be checked and customised. You can select from the lists on the left hand side, then review the existing options. Options can be removed from the context, so they will not be included in this questionnaire, and you have the option to add new options by clicking the "add new" button. For each choice list entry, you will need to add a name and label, then click "create", or "create and add another" to save the entry.
                </p>

                <p class="mb-2">
                    Each list page includes the option to view the questions that will use these answer options. Check these to ensure you provide suitable options.
                </p>

     
            </div>
        </x-slot:instructions>
    </x-instructions-sidebar>

    <x-filament::section class="mb-4" :heading="$choiceListName">
        {{ $choiceList->description ?? "The list below includes a set of possible responses to some of the survey questions. Please review the list and make sure it is appropriate for your context. You may add new entries and remove existing entries if they are not relevant."}}
    </x-filament::section>
    <x-filament::section collapsed heading="Questions that use this list within HOLPA" icon="heroicon-o-information-circle" collapsible>

        <ul>
            <li class="flex w-full border-b mb-2">
                <span class="w-1/4 text-right font-bold pr-4">Variable Name:</span>
                <span class="w-3/4 font-bold">Question text</span>
        @foreach($surveyRows as $surveyRow)
            <li class="flex w-full mb-2">
                <span class="w-1/4 text-right pr-4">{{ $surveyRow['name'] }}:</span>
                <span class="w-3/4">{!! \Illuminate\Support\Str::markdown($surveyRow['label']) !!}</span>
            </li>
        @endforeach

        </ul>
    </x-filament::section>


</x-filament-widgets::widget>
