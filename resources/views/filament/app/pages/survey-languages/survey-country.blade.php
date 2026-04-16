<x-filament-panels::page>

<x-instructions-sidebar>
        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>

            {{-- <div class="pr-4 content-center  mx-auto my-4">
                <iframe class="rounded-3xl" src="https://www.youtube.com/embed/TODO_ADD_VIDEO_ID" style="width: 560px; height: 315px;" frameborder="0" allowfullscreen></iframe>
            </div> --}}
            <div class="mx-12 mb-4">

                <h5>Select country and languages </h5>
                <p class="mb-2">
                    Start by selecting the country for the survey. You can click in the box and either scroll through the dropdown list or start typing to narrow down the options and find your country. If the country you need is not listed, you can use the "plus" button to add it. This will require you to input the country name and some additional details.
                </p>
                <p class="mb-2">
                    You can then start adding the languages in which you will conduct your survey. If you are going to run your survey in multiple languages, all of them need to be added here. Start typing in the box to find and add languages.
                </p>
                 <p class="mb-2">
                    Click "Save and return" when you have finished.
                </p>


            </div>
        </x-slot:instructions>
    </x-instructions-sidebar>
    <div class="container mx-auto  ">
        <div class="surveyblocks pt-16 pb-24 mb-32 px-12 lg:px-16">
            <h3>Instructions</h3>
            <p class="mb-8">Please select the country and languages for your survey below.</p>

            {{ $this->form }}

            <a href="{{ \App\Filament\App\Pages\SurveyLanguages\SurveyLanguagesIndex::getUrl() }}" class="buttona block max-w-sm mx-auto md:inline-block mb-6 md:mb-0 mt-12">Save and Return</a>
        </div>
    </div>
</x-filament-panels::page>
