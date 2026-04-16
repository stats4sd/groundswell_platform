<?php

use App\Filament\App\Pages\SurveyDashboard;

$surveyDashboardUrl = SurveyDashboard::getUrl();
?>

<x-filament-panels::page>

<x-instructions-sidebar>
        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>

            {{-- <div class="pr-4 content-center  mx-auto my-4">
                <iframe class="rounded-3xl" src="https://www.youtube.com/embed/TODO_ADD_VIDEO_ID" style="width: 560px; height: 315px;" frameborder="0" allowfullscreen></iframe>
            </div> --}}
            <div class="mx-12 mb-4">

                <h5>Survey translations </h5>

                    <p class="mb-2">  
                        HOLPA is available in multiple languages. On the translations page, for each of the languages you selected previously, you will need to select the translation to use. Click "Select translation" to show the available translations for a language. 
                    </p>
                    <p class="mb-2">  
                        If there is no existing translation, or the available translations are not suitable for your survey location, you should add a new translation. You may wish to duplicate an existing translation to use as a starting point. 
                    </p>
                    <p class="mb-2">  
                        To add a new translation, click on "add new" and give it a clear name. The translation will appear in the table. You can then use the "view/edit" menu to download the translation template. Add the translated text in the indicated column in the xls files, then reupload them using the same menu. Once both forms have translated versions uploaded, the translation will be available for use. When editing or adding a translation, make sure you download the file for the language you wish to edit, and make the changes in that file. If the column headers do not match the original file or if the column for the translated text is left blank, you will see an error message when you upload the files.
                    </p>
                    <p class="mb-2">  
                        If you click on view/edit translation, this will allow you to download the xls files with the translated text. 
                        You can edit translations that your team has added. To do this, download the existing translation files and follow the same process as when adding a translation: make changes in the column for the destination language and upload the edited file through the same menu. Note that the translation is to reflect the text <span class="font-semibold">as written in the HOLPA survey</span>; this is not the appropriate place to customise the content of the survey. Translations added by other teams or from the original ODK forms cannot be directly edited, but you can duplicate the translation to make a new version with your changes. 
                    </p>
                    <p class="mb-2">  
                        The HOLPA platform will make user-uploaded translations available to all other teams. 
                    </p>
                    <p class="instructions_note">  
                    <span class="font-semibold"> NOTE: </span> The platform assumes that you are working within one country. If you are conducting HOLPA across multiple countries, you will need to create separate teams within this platform for each country. Please contact the HOLPA support team if you require assistance with this.
                    </p>

            </div>
        </x-slot:instructions>
    </x-instructions-sidebar>

{{--        instructions1='TK'--}}
{{--        instructionsmarkcomplete=' you have selected and (where needed) uploaded the completed translation for each language you intend to use for the survey. '--}}
{{--        videoUrl='https://www.youtube.com/embed/VIDEO_ID'--}}


    <div id="languages">
        <!-- Main Section -->
        <div class=" container mx-auto xl:px-12 ">
            <div class="surveyblocks p-12 lg:p-16  h-full">

                <div class="mb-8 dropdown_tables">
                    <div class="pb-4">
                        <h3 class=" ">Survey Languages</h3>
                        <p class="mb-8">
                   
                      
                    Below are the languages you selected for your survey. For each one, click on "Select translation" to see available translations, and either select an appropriate option from the list or add the translation for the language. 
                </p>
 </div>
                    @foreach($languages as $language)
                        <livewire:survey-languages.team-translation-entry :language="$language" :key="$language->id" :team="$team"/>
                    @endforeach

                </div>
            </div>
        </div>
    </div>

</x-filament-panels::page>
