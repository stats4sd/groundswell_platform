<div class="space-y-8">
    <p>{{ t("You can review the text for the ODK forms by downloading the files below.") }}</p>

    @if($locale->is_default)
        <p>{{ t("This translation is from the original ODK Forms, and cannot be directly edited. If you wish to create your own version of") }} {{ $locale->language->name }}, {{ t("you may add a new blank translation or duplicate this one and edit it.") }}</p>
    @elseif($locale->createdBy !== \Stats4sd\FilamentOdkLink\Services\HelperService::getCurrentOwner())
        <p>{{ t("This translation was uploaded by another team. You cannot edit this translation directly, but you may duplicate it and edit your version if you wish.") }}</p>
    @else
        <p>{{ t("To edit this translation, click edit below. To keep this version as-is and create a new version to edit, click duplicate.") }}</p>
    @endif

    <div class="flex w-full justify-around">
        <div class="w-1/4">
            {{ $this->downloadHouseholdAction }}
        </div>

        <div class="w-1/4">
            {{ $this->downloadFieldworkAction }}
        </div>
    </div>

    {{-- TODO: add 'download blank version'?? --}}


</div>
