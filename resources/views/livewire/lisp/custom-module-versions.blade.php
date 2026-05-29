<div class="dropdown_tables">

    <div class="text-lg font-bold text-green mb-8">
        {{ t("ADD CUSTOM SURVEY QUESTIONS") }}
    </div>

    <div class="space-y-4 mb-8">
        <p>{{ t("Below are the local indicators that have not been mapped to an existing global HOLPA indicator. For each indicator, please add one or more questions that will be included into the survey.") }}</p>
        <p>{{ t("You can either add the questions directly using the \"Add Question\" buttons below, or download the Excel template, write your questions in a normal ODK \"Xlsform\" format, and upload the file. If you are confident writing ODK forms in Excel, this option will give you more flexibility, as you can add any feature that ODK supports.") }}</p>
    </div>

    <button wire:click="downloadTemplate" class="buttona">{{ t("Download Xlsform Template") }}</button>

    <h3 class="my-8">{{ t("Import Questions from an ODK Xlsform (Excel)") }}</h3>

    {{ $this->form }}

    <h3 class="my-8">{{ t("Add / Edit Questions Directly") }}</h3>
    @foreach($unmatchedLocalIndicators as $localIndicator)

        <livewire:lisp.local-indicator-question-form :localIndicator="$localIndicator" :key="$localIndicator->id">

    @endforeach
</div>
