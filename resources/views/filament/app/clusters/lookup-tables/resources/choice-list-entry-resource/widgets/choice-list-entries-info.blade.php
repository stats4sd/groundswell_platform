@php $instructions = tfile('choice-list-entries'); @endphp

<x-filament-widgets::widget>
<x-instructions-sidebar>
        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>
    </x-instructions-sidebar>

    <x-filament::section class="mb-4" :heading="$choiceListName">
        {{ $choiceList->description ?? t("The list below includes a set of possible responses to some of the survey questions. Please review the list and make sure it is appropriate for your context. You may add new entries and remove existing entries if they are not relevant.") }}
    </x-filament::section>
    @php $questionsHeading = t('Questions that use this list'); @endphp
    <x-filament::section collapsed :heading="$questionsHeading" icon="heroicon-o-information-circle" collapsible>

        <ul>
            <li class="flex w-full border-b mb-2">
                <span class="w-1/4 text-right font-bold pr-4">{{ t("Variable Name:") }}</span>
                <span class="w-3/4 font-bold">{{ t("Question text") }}</span>
        @foreach($surveyRows as $surveyRow)
            <li class="flex w-full mb-2">
                <span class="w-1/4 text-right pr-4">{{ $surveyRow['name'] }}:</span>
                <span class="w-3/4">{!! \Illuminate\Support\Str::markdown($surveyRow['label']) !!}</span>
            </li>
        @endforeach

        </ul>
    </x-filament::section>


</x-filament-widgets::widget>
