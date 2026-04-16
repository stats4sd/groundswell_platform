<?php
?>

<x-filament-panels::page>
    <div class="container mx-auto xl:px-12 ">
        <div class="surveyblocks pt-16 pb-24 mb-32 px-12 lg:px-16">

            <div class="text-sm ">
                <h3 class="mb-6">About the LISP workshop</h3>
                <p>The local indicator selection process (LISP) is a vital part of the localisation of the HOLPA tool. It involves conducting a workshop with local farmers and stakeholders to brainstorm and prioritise a set of local indicators to include in the HOLPA tool that could be used to monitor the types of changes they want to see in their farms and landscapes. This ensures locally relevant indicators are included in the assessment, and thus the evidence that is generated is useful to people where it is applied.</p>
                <p class="pt-4">Participants brainstorm potential indicators for each one of the same dimensions as the HOLPA Performance module (agronomic, social, environmental), that could be used
                    to monitor the types of changes they want to see in their farms and landscapes. After agreeing on a set of evaluation criteria (such as importance, ease of measurement,
                    liklihood of changing), participants evaluate the potential indicators to arrive at a set of approximately three local indicators per performance dimension to include in
                    the localised HOLPA tool.
                </p>

                <ol class="list-disc py-4 px-10">
                    <li class="mb-2">
                        <span class="font-semibold">Activity 0: Introductions and coming together </span>
                        <br>
                        <p>The very first activity involves creating a conducive environment for all stakeholders to actively participate and express freely. The best way of doing this will depend on the context – the cultural context as well as the social or political context of the group you are working with. Some of the suggested agenda items for this activity include introduction of participants, observing customary rituals, norms or formalities and presenting workshop objectives, goals and rules.</p>
                    </li>
                    <li class="mb-2">
                        <span class="font-semibold">Activity 1: Anchoring the workshop (Where are we and where do we want to go? Review of the ALL’s Context and Vision)</span><br>
                        <p>The first core activity in LISP is to anchor the process in the local context of the ALL and the vision and goals for the ALL. This could include sharing results of a context or situational analysis, presenting back results of a survey or inventory that was done, or a review of previous activities in the area. The goal is to develop a common understanding of the boundaries and key features of the landscape where we will be working together. </p>
                    </li>
                    <li class="mb-2">
                        <span class="font-semibold">Activity 2: Indicator brainstorming (How will we know if we get there?) </span><br>
                        <p>This session will first introduce the concept of indicators and why people use indicators. We use the simple definition that an indicator is something that we can measure that gives us information about something we’d like to know. The participants will then be divided into groups and guided to brainstorm a long list of possible AE indicators that will answer the questions of “How will we know if we achieve our goal of achieving sustainable agriculture?”. The brainstorming will be structured around the four main themes of the HOLPA assessment framework: Social, Economic, Environmental and Agricultural. Each group will have an opportunity to brainstorm for a few minutes on each theme.</p>
                    </li>
                    <li class="mb-2">
                        <span class="font-semibold">Activity 3: Indicator criteria and evaluation (What makes a good indicator?) </span><br>
                        <p>In this activity, participants and facilitators will come up with criteria against which to evaluate the indicators that have been long-listed in the previous step. To perform the prioritization, have the stakeholders for four groups, one for each theme of the assessment (Environmental, Economic, Social and Agricultural). The group will then evaluate the long-list indicators against the chosen criteria.</p>
                    </li>
                    <li class="mb-2">
                        <span class="font-semibold">Activity 4:  Agreeing on priority local indicators </span><br>
                        <p>A final discussion should be facilitated to gain consensus amongst the stakeholders on the priority indicators selected under each of the four themes. There should be an overall agreement on the fact that the selected indicators are the most appropriate and measurable to determine the progress towards the envisioned future in transitioning towards agroecology. </p>
                    </li>
                    <li class="mb-2">
                        <span class="font-semibold">Activity 5: Workshop Close </span><br>
                        <p>A final discussion should be facilitated to gain consensus amongst the stakeholders on the priority indicators selected under each of the four themes. There should be an overall agreement on the fact that the selected indicators are the most appropriate and measurable to determine the progress towards the envisioned future in transitioning towards agroecology. </p>
                    </li>
                </ol>
            </div>
            <h3 class="mb-6">Downloads</h3>
            <div>
                <x-download-section :url="url('files/HOLPA_indicator_template.xlsx')">
                    <x-slot:heading>Indicator template</x-slot:heading>
                    <x-slot:description>To create the local indicators and incorporate them into the HOLPA survey,
                        <b>you will need to use this template file.</b> This will ensure the required information for the indicators is included, and you will need to use it to upload your indicators to include in your survey.
                    </x-slot:description>
                    <x-slot:buttonLabel>Download .xlsx</x-slot:buttonLabel>
                </x-download-section>
            </div>

            <h3 class="mb-6">Optional workshop supporting materials</h3>

            <div>
                <x-download-section :url="url('#')">
                    <x-slot:heading>Slideshow template</x-slot:heading>
                        <x-slot:description>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut ac venenatis elit. Vivamus non urna ac turpis hendrerit tincidunt ut eget risus.</x-slot:description>
                        <x-slot:buttonLabel>Download .xlsx</x-slot:buttonLabel>
                </x-download-section>

                <x-download-section :url="url('#')">
                    <x-slot:heading>Global survey indicators</x-slot:heading>
                        <x-slot:description>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut ac venenatis elit. Vivamus non urna ac turpis hendrerit tincidunt ut eget risus.</x-slot:description>
                        <x-slot:buttonLabel>Download .xlsx</x-slot:buttonLabel>
                </x-download-section>
            </div>

            <div class="flex justify-center my-8">
                <a href="url_to_be_added_here"
                   class="buttonb ">
                    Download all workshop materials .zip
                </a>
            </div>

            <h3 class="mb-6">Next steps</h3>

            <div class="text-gray-800 text-sm leading-relaxed ">
                <p>Following completion of the workshop and identification of the tools, the next step will guide you through the process of mapping your selected
                    indicators to available optional indicators, and if necessary uploading new indicators.
                </p>
            </div>

        </div>
    </div>
</x-filament-panels::page>
