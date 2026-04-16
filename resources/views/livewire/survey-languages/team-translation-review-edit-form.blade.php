<div>

     {{ $this->form }}

        <div class="my-4 flex justify-end">
            <button class="buttonb" wire:click="cancel">Cancel</button>
            <button class="buttona" wire:click="duplicate">Duplicate</button>

            @if($canSave)
                <button class="buttona" wire:click="submit">Submit</button>
            @else
                <button class="buttona" disabled>Submit</button>
            @endif
        </div>

</div>
