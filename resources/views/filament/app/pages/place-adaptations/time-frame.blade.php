
<x-filament-panels::page>
<x-instructions-sidebar>
        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>

            {{-- <div class="pr-4 content-center  mx-auto my-4">
                <iframe class="rounded-3xl" src="https://www.youtube.com/embed/TODO_ADD_VIDEO_ID" style="width: 560px; height: 315px;" frameborder="0" allowfullscreen></iframe>
            </div> --}}
            <div class="mx-12 mb-4">

<h5>Time frame </h5>
 <p class="mb-2">    

The first thing you can customise is the time frame that is asked about for questions concerning the recent past. By default, this time frame is "In the last 12 months". However, for your survey, it might make more sense to ask the questions about "last season" or "last year".  
</p>
 <p class="mb-2"> 
The page shows the questions in the survey that use the time frame. Whatever phrase is used for the time frame will be inserted into the question in place of the "${time_frame}"" text placeholder. Look through them, determine what time frame is most appropriate and, if you decide to change it, update the timeframe text in the box. Your entry will be automatically saved and the question text in your survey form will be updated.
</p>
               
            </div>
        </x-slot:instructions>
    </x-instructions-sidebar>
<div class="container mx-auto  ">
        <div class="surveyblocks pt-16 pb-24 mb-32 px-12 lg:px-16">

    <p>In the HOLPA Household Survey, there are questions to ask about a specific time frame. By default, this time frame is "<b>In the last 12 months</b>". However, that is not suitable for all situations. For your survey, it might make more sense to ask the questions about "<b>Last Season</b>" or "<b>Last Year</b>".
    </p>
    <p class="mb-12">Below is the complete list of questions that use this time frame. Please review the questions. If you wish to change the time frame for your survey, update the text in the text box below. Your entry will be automatically saved and the question text in your survey form will be updated.</p>

    {{ $this->form }}
<div class="h-12"></div>
    {{ $this->table }}
</div></div>
</x-filament-panels::page>
