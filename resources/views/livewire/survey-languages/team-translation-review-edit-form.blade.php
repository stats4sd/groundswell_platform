<div>

     {{ $this->form }}

        <div class="my-4 flex justify-end">
            <button class="buttonb" wire:click="cancel">{{ t("Cancel") }}</button>
            <button class="buttona" wire:click="duplicate">{{ t("Duplicate") }}</button>

            @if($canSave)
                <button class="buttona" wire:click="submit">{{ t("Submit") }}</button>
            @else
                <button class="buttona" disabled>{{ t("Submit") }}</button>
            @endif
        </div>

</div>
