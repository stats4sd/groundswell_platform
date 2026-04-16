<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;

class EditSubmissionOnCentral extends Component
{
    public Submission $submission;
    public ?bool $authenticatedWithCentral;

    public function mount()
    {
        $response = Http::get($this->submission->enketo_edit_url)
            ->status();

        $this->authenticatedWithCentral = $response !== 403;
    }

    public function render()
    {
        return view('livewire.edit-submission-on-central');
    }
}
