<x-filament-panels::page>
    <div class="container mx-auto xl:px-12 !mb-4">

        <x-instructions-sidebar>

            <x-slot:heading>Instructions</x-slot:heading>
            <x-slot:instructions>
                {{-- <div class="pr-4 content-center mx-auto my-4">
                <iframe class="rounded-3xl" src="https://www.youtube.com/embed/TODO_ADD_VIDEO_ID" style="width: 560px; height: 315px;" frameborder="0" allowfullscreen></iframe>
                </div> --}}
                <div class="mx-12 mb-4">

                    <h5>Customise indicators</h5>
                    <p class="mb-2">
                        The customise indicators page allows you to incorporate the local indicators identified in your workshop into the customised HOLPA tool by uploading the local indicators, matching them where possible with available HOLPA indicators, and adding new custom indicators for any that remain.
                    </p>
                    <p class="mb-0">
                        When you go to the customise indicators page, you have three options. You will want to work left to right to start with, but you can return at any point and make changes in any of the sections. </p>
                    <ul class="instructions_list list-disc mt-0 ">
                        <li>
                            <span class="font-semibold">Upload local indicators</span><br>
                            This is where you upload the completed template containing the local indicators that were selected in the workshop. Drag and drop or click to select the file you want to upload, then click the upload button to confirm. Once you have uploaded a file, you will see the details and have the option to delete it and upload a new file.
                        </li>
                        <li>
                            <span class="font-semibold">Match with global indicators</span>
                            <br>
                            This option allows you to browse through the list of core and optional indicators already present in the global HOLPA survey. If your indicators match up to the ones already available, you can add them easily by matching them here.
                            <br>
                            Click on an indicator on the left hand side. The available indicators within the corresponding theme will appear on the right. Read through and, if there is one that is the same as your indicator (or "close enough", depending on what your team and stakeholders may decide), then select that as a match.
                            Do this for each of the indicators. There may not be suitable matching indicators for all of them; remaining indicators can be incorporated into the survey using the "Add custom survey questions" option.
                        </li>
                        <li>
                            <span class="font-semibold">Add custom survey questions</span><br>
                            This may be more resource intensive in terms of data collection, and reduces the comparability of results, so consider each one you choose to add carefully as a compromise between local relevance and global comparability.
                            <br>
                            You will see the remaining unmatched indicators in a table. You have the option to either import questions in bulk using an XLSform, or manually add questions for each indicator.
                            <ul class="instructions_list list-disc ">
                                <li>
                                    If you are confident writing ODK forms in Excel, them importing questions will give you more flexibility (for example, with different question types). To import questions, click the "Download XLSform template" button, and fill in the template. You will need to write your questions in a normal ODK XLSform format, and use the first column in the template to select which indicator each question is associated with. When you have completed the form, return to the same page to upload it, and you will see the newly added questions in the table below.
                                </li>
                                <li>
                                    You can add questions manually using the "Add question" button for each indicator in the list. Select the question type, and then fill in the fields as needed. When you save changes to the question, it will appear in the list.
                                </li>
                                <li>
                                    On the left hand side of the table, there is a button you can drag to reorder the questions within an indicator.
                                </li>
                            </ul>
                        </li>
                        <li>
                            <span class="font-semibold">Place custom questions in survey</span><br>
                            This option allows you to indicate where in the survey the custom questions should be placed. In the left column is a list of your local indicators, and you can click to drop down to see the questions you have added for each one. On the right hand side are all the modules of the Household and Fieldwork surveys. To place the questions, drag an indicator from the list on the left to the desired position in the list on the right.
                            <br/><br/>
                            The order of the questions within each module cannot be changed here. To change the order of questions within a module, you will need to go back to the "Add custom survey questions" section and reorder them there.
                            <br/><br/>
                            Once you have placed all your custom questions, click "Confirm Ordering" to finalize the order and add your custom questions to the surveys for testing. If you need to reset the order at any time, click "Reset Ordering" to revert to the default ordering (this will remove your local indicator modules from the survey forms).
                        </li>
                    </ul>


                </div>

            </x-slot:instructions>

        </x-instructions-sidebar>
        <div class="surveyblocks pb-24 mb-32 pt-12 px-12">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4   h-max mb-12">
                <a wire:click="setActiveTab('local')" class="{{ $activeTab === 'local' ? 'tabbuttons' : '' }}  rounded-2xl cursor-pointer lisptabs bg-gray-100 ">
                    <x-rounded-square>
                        <x-slot:heading>Upload local indicators</x-slot:heading>
                        <x-slot:description>Upload the local indicators you identified in the LISP workshop.</x-slot:description>
                    </x-rounded-square>
                </a>
                <a wire:click="setActiveTab('match')" class="{{ $activeTab === 'match' ? 'tabbuttons' : '' }}  rounded-2xl cursor-pointer lisptabs bg-gray-100">
                    <x-rounded-square>
                        <x-slot:heading>Match with existing global indicators</x-slot:heading>
                        <x-slot:description>Browse the list of indicators already available in the HOLPA global survey, and match them to your identified local indicators.</x-slot:description>
                    </x-rounded-square>
                </a>
                <a wire:click="setActiveTab('custom')" class="{{ $activeTab === 'custom' ? 'tabbuttons' : '' }}  rounded-2xl cursor-pointer lisptabs bg-gray-100">
                    <x-rounded-square>
                        <x-slot:heading>Add custom survey questions</x-slot:heading>
                        <x-slot:description>The local indicators that are not matched to a HOLPA global indicator should be reviewed. For each indicator, you can add one or more questions to the survey to allow you to calculate the indicator.</x-slot:description>
                    </x-rounded-square>
                </a>
                <a wire:click="setActiveTab('ordering')" class="{{ $activeTab === 'ordering' ? 'tabbuttons' : '' }}  rounded-2xl cursor-pointer lisptabs bg-gray-100">
                    <x-rounded-square>
                        <x-slot:heading>Place custom questions in survey</x-slot:heading>
                        <x-slot:description>Once you have defined the questions to ask, you need to insert them into either the Household or Fieldwork survey.</x-slot:description>
                    </x-rounded-square>
                </a>
            </div>

            <!-- Content -->
            <div class="px-6">
                @if ($activeTab === 'local')

                    <livewire:lisp.upload-local-indicators/>
                @elseif ($activeTab === 'match')
                    @include('livewire.lisp.match-indicators')
                @elseif ($activeTab === 'custom')
                    <livewire:lisp.custom-module-versions/>
                @elseif ($activeTab === 'ordering')
                    <livewire:lisp.custom-module-ordering/>
                @else
                    <div class="mx-auto w-max">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-6 h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                        </svg>
                        <span class="ml-2 font-semibold">Select a tab above to begin </span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
