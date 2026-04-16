<x-filament-panels::page class="px-10 h-full">

    <x-instructions-sidebar>
        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>

            <div class="mx-12 mb-4">

                <h5 class="mt-0">Initial pilot test</h5>
                <p class="my-2">
                    Following customisation, a pilot test should be conducted to check the sense and functionality of the survey. On this page, you will find the QR code to scan to start testing the survey using ODK.
                    Note that this QR code should only be used for this initial testing - the 'data collection' section contains a different link for the full pilot and live data collection, and it is important to use the correct version of the survey.
                </p>
                <p class="mb-2">
                    The initial pilot can be completed by a member of your team with at least one local researcher or practitioner. To conduct the test:
                <ol class="mb-4 ml-6 list-decimal">
                    <li class="mb-1">
                        Make sure you have made all the intended customisations up to this step of the process.
                    </li>
                    <li class="mb-1">
                        Use the QR codes below to access the draft versions of the survey on an appropriate device with the ODK collect application installed and set up.
                    </li>
                    <li class="mb-1">
                        The HOLPA user and the local practitioner should work through the survey in the ODK collect app, checking and answering all of the questions. The aim is to:

                        <ul class=" ml-6 list-disc">
                            <li class="mb-1">
                                Check that the survey layout and dependencies are correctly coded; for example, you should check that mandatory questions are not showing as optional.
                            </li>
                            <li class="mb-1">

                                Make sure all questions and response options are clear for respondents in the local context.
                            </li>
                            <li class="mb-1">

                                Identify if hints or examples are needed for any sections that may cause confusion or be misunderstood.
                            </li>
                        </ul>
                    </li>
                    <li class="mb-1">
                        When you submit data using the test survey, it will appear in the "Draft submissions" table, so you can confirm that the data has been submitted and recognised.
                    </li>
                </ol>
                <p class="mb-2">
                    Once you have completed this process, you may need to return to some of the actions within this step or previous steps (such as to edit the survey translation). You can repeat this pilot test as many times as you choose.
                    Be aware that each time you make a change to the form, an updated form is generated. This can take a few minutes, so you may need to wait while this process completes to access the latest version of the test forms. Any submissions to the test forms are temporary and will be overwritten when the forms are updated.
                </p>

                <x-red-alert-box>
                    <x-slot:content>
                        The QR codes below are
                        <span class="font-semibold">DRAFTS</span>.<br>
                        Please
                        <span class="font-semibold">do not share </span>these codes with enumerators!<br>
                        <span class="font-semibold"> All submissions to the test forms are temporary </span> and will be reset whenever you take an action using the survey dashboard that changes the forms.<br>
                        Do not use these forms for live data collection.
                    </x-slot:content>
                </x-red-alert-box>
                <h5> Mark this section as complete when:</h5>
                <p class="mb-2">
                    You have conducted the technical test and are happy with the changes to the forms and the technical functioning of the survey up to this point.
                </p>

            </div>
        </x-slot:instructions>
    </x-instructions-sidebar>

    <div class="container mx-auto xl:px-12">
        <div class="surveyblocks py-12 px-12 lg:px-16">

            <div class="mb-4">

                <h3 class="mb-4"> Testing the surveys</h3>

                <p class="mb-2">
                    Once your team has made customisations to the form, an "initial pilot test" should be carried out. This is predominantly a test of the sense and technical functionality of the survey.
                    The initial pilot can be completed by a member of your team with at least one local researcher or practitioner. To conduct the test:
                </p>
                <ol class="mb-4 ml-12 list-decimal">

                    <li class="mb-1">
                        Use the QR codes below to access the draft versions of the survey on an appropriate device with the ODK collect application installed.
                    </li>
                    <li class="mb-1">
                        The HOLPA user and the local practitioner should work through the survey in the ODK collect app, checking and answering all of the questions. The aim is to:

                        <ul class="my-2 ml-12 list-disc">
                            <li class="mb-1">
                                Check that the survey layout and dependencies are correctly coded; for example, that mandatory questions are not showing as optional.
                            </li>
                            <li class="mb-1">

                                Make sure all questions and response options are clear for respondents in the local context.
                            </li>
                            <li class="mb-1">

                                Identify if hints or examples are needed for any sections that may cause confusion or be misunderstood.
                            </li>
                        </ul>
                    </li>
                    <li class="mb-1">
                        When you submit data using the test survey, it will appear in the
                        <a href="#draft_subs" class="font-semibold text-green"> "Draft submissions" </a> table further down this page, so you can confirm that the data has been submitted and recognised.
                    </li>
                </ol>
                <p class="mb-2">
                    Once you have completed this process, you may need to return to some of the actions within this step or previous steps (such as to edit the survey translation). You can repeat this pilot test as many times as you choose.
                </p>


                <div class="" id="text_forms">
                    <h3 class="mb-4 mt-8"> Access the test forms</h3>
                    The QR codes below use ODK Central's "Draft" feature to allow you to access test versions of the surveys. These versions always have the latest adaptations, and allow you to test changes without affecting the published versions that are shared with your enumerators.
                </div>

                <div class="my-4 md:mx-16 bg-red-100 border-2 border-red-700 text-red-700 px-4 py-3 rounded-xl relative flex flex-col md:flex-row items-center" role="alert">

                    <x-heroicon-o-exclamation-triangle class="w-16 sm:w-20 flex-shrink-0 text-red mb-2 md:mb-0"/>
                    <div class="md:ml-8 py-auto">
                        The QR codes below are
                        <span class="font-semibold">DRAFTS</span>.
                        Please
                        <span class="font-semibold">do not share </span>these codes with enumerators!
                        <br>
                        <span class="font-semibold"> All submissions to the test forms are temporary </span> and will be reset whenever you take an action using the survey dashboard that changes the forms.
                    </div>
                </div>
                <div class="text-center mt-8 p-4 grid md:grid-cols-2 grid-cols-1 justify-around align-items-center space-x-0 md:space-x-8 md:space-y-0 space-y-4">
                    @foreach($xlsforms as $xlsform)

                        @if($xlsform->draft_needs_update)

                            <div class="border border-gray-600 rounded-lg p-1 flex flex-col items-center justify-center space-y-4 h-100 px-4">
                                <div class="mx-auto"></div>
                                <div class="mx-auto">This XLSForm is currently being updated. This may take 2-3 minutes. The QR code will appear when the new draft is ready. The form processing will continue even if you leave this page and return later.</div>
                                <div role="status">
                                    <svg aria-hidden="true" class="w-8 h-8 text-gray-200 animate-spin dark:text-gray-600 fill-blue" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                                        <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                                    </svg>
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <h3>{{ $xlsform->title }}</h3>
                            </div>

                        @else

                            <div class=" rounded-lg p-4 flex flex-col items-center justify-center space-y-4 space-x-4 h-100">
                                <div class="mx-auto">{{ QrCode::size(150)->generate($xlsform->draft_qr_code_string) }}</div>
                                <h3>{{ $xlsform->title }}</h3>
                                <h5 class="mx-auto font-normal">SCAN QR Code in ODK Collect</h5>
                            </div>
                        @endif
                    @endforeach
                </div>


            </div>

            <div class="pb-8" id="draft_subs">Any submissions to these DRAFT versions will appear below, so you can confirm that the data you have submitted has been recognised. Note that these DRAFTs are intended as a technical test of the forms themselves, and the DRAFT submissions are not intended to be kept and processed. To conduct a full pilot, including data export and a test of the analysis process, please go to the
                <a class="font-semibold text-green" href="{{ App\Filament\App\Pages\Pilot\PilotIndex::getUrl() }}">Localisation: Pilot</a> page.
            </div>

            {{ $this->table }}

        </div>
    </div>
</x-filament-panels::page>
