<div class="w-full">


    @if($authenticatedWithCentral)

        <iframe
            src="{{ $submission->enketo_edit_url }}"
            style="height: 80vh; min-height: 500px"
            class="w-full"
        />

    @else

    @endif

</div>
