@php

    $locations = \App\Models\SampleFrame\Location::where('owner_id', \Stats4sd\FilamentOdkLink\Services\HelperService::getCurrentOwner()->id)->get();

    $submissions = $getRecord()->submissions;


    // comment below code and hardcode temporary for testing
    // TODO: revise to corresponding ODK variable in household form and fieldwork form


    $submissionsByLocations = $submissions->map(function(\Stats4sd\FilamentOdkLink\Models\OdkLink\Submission $submission) {

    $submission->location_id = $submission->content['context']['location']['village_name'];

    return $submission;
    })
    ->groupBy('location_id');


    /*
    $submissionsByEnumerators = $submissions->map(function(\Stats4sd\FilamentOdkLink\Models\OdkLink\Submission $submission) {

    $submission->enumerator_id = $submission->content['survey_start']['inquirer'];

    return $submission;
    })->groupBy('enumerator_id');
    */


    $submissionsByEnumerators = $submissions;

@endphp

<div class="grid grid-cols-2 gap-8">

    <x-filament::section>
        <x-slot name="heading">
            <!-- TODO -->
            <!-- refer to below comment in PR 64 -->
            <!-- https://github.com/stats4sd/holpa-platform/pull/64#pullrequestreview-2513729072 -->
            <b>Submissions By Location</b>
        </x-slot>

        @foreach($submissionsByLocations as $key => $locationFromSubmission)
            <div class="grid grid-cols-3 gap-3">
                <b class="text-right">{{ $locations->firstWhere('id', $key)?->name ?? $key }}</b>
                <span class="col-span-2">{{ $locationFromSubmission->count() }}</span>
            </div>
        @endforeach

    </x-filament::section>

</div>
