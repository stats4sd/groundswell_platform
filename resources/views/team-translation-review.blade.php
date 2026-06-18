<div class="space-y-2 translation-modal">
    <p>{{ t("You can review the text for both ODK forms by downloading the files below.") }}</p>

    @if($locale->is_default)
        <p>{{ t("This translation is from the original ODK Forms, and cannot be directly edited. If you wish to create your own version of") }} {{ $locale->language->name }}, {{ t("you may add a new blank translation or duplicate this one and edit it.") }}</p>
    @elseif($locale->creator->id !== \Stats4sd\FilamentOdkLink\Services\HelperService::getCurrentOwner()->id)
        <p>{{ t("This translation was uploaded by another team. You cannot edit this translation directly, but you may duplicate it and edit your version if you wish.") }}</p>
    @else
        <p>{{ t("To edit the translations:") }}
        <ol class="list-inside list-decimal">
            <li>{{ t("Download the existing translations file. Alternatively, download the blank translations file to start fresh.") }}</li>
            <li>{{ t("The Excel file contains a list of every string in the form. The orange columns should not be changed. Please enter the translated text into the final column, titled") }} {{ $locale->language_label }}</li>
            <li>{{ t("Re-upload the completed Excel file below. If there is already a file uploaded, you can delete it and upload a replacement.") }}</li>
        </ol>
        </p>
        <div class=" border-b py-4"></div>
    @endif

    <livewire:survey-languages.team-translation-review-edit-form :locale="$locale" :team="$team" :canMaintain="$canMaintain ?? false"/>


</div>
