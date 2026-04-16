<?php

use App\Filament\App\Pages\PlaceAdaptations\InitialPilot;

?>

@props([
  'actions' => [],
  'breadcrumbs' => [],
  'heading' => '',
  'subheading' => null,
])

<div class="h-80 w-screen full-width -mt-8 bg-black">
    <img src="/images/landscape.jpg" alt="Background Image" class="w-full h-full md:max-h-[80vh] lg:max-h-[70vh] object-cover absolute" style="object-position:center; z-index: 0">

    <!-- Overlay Content -->
    <div class="relative inset-0 f h-full md:max-h-[80vh] lg:max-h-[70vh] bg-black bg-opacity-50 pt-6 lg:pt-2 z-8 px-8 md:px-24 py-20 pr-36">

        <!-- Headings -->
        <div class="container mx-auto xl:px-28">
            <h2 class="text-white text-5xl mb-2 lg:mb-4 mt-20 font-extralight" style="letter-spacing: 0.3em;">HOLPA</h2>
            <h1 class="text-hyellow text-4xl sm:text-5xl lg:text-6xl mb-2 lg:mb-4">Survey Dashboard</h1>
        </div>
    </div>
</div>

<div class=" w-screen full-width -mt-8 bg-green">
    <div class="container mx-auto xl:px-28 !flex flex-col md:flex-row  py-2 md:py-8 content-center">
        <div class="md:w-1/2 py-4 md:py-0">
            <h3 class="text-white"> Shortcut: test or preview survey</h3>
            <p class="font-normal text-white"> Jump to the ‘initial pilot’ page to access a draft version of your survey questionnaires.</p>
        </div>
        <div class="md:w-1/2 py-4 md:py-0 text-center md:text-right pt-0 relative content-center mt-4 md:mt-0">
            <a class="button bg-white text-green font-semibold uppercase rounded-full py-2 px-4 " href="{{ InitialPilot::getUrl() }}">Preview</a>
        </div>
    </div>
</div>
