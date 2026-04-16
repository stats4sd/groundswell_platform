<x-slot:vite-scripts>
    @vite('resources/js/public-map.js')
</x-slot:vite-scripts>

<div class="explore-page">


    <div class="relative h-auto">
        <!-- Background Image (absolutely positioned to go as a backdrop) -->
        <img src="images/landscape.jpg" alt="Background Image" class="w-full h-36 object-cover absolute" style="object-position:center; z-index: 0">

        <!-- Overlay Content -->
        <div class="relative inset-0 flex text-center items-center justify-center h-36 bg-black bg-opacity-50 lg:pt-2 z-10">
            <div class="w-max flex flex-col items-center relative">
                <!-- Headings -->
                <div class="relative flex items-center mb-0 text-center px-4">
                    <h2 class="text-white text-5xl mb-2 lg:mb-4 font-extralight" style="letter-spacing: 0.3em;">HOLPA</h2>
                </div>
                <div class="relative flex items-center mb-0 text-center px-4">
                    <h3 class="text-white text-3xl mb-2 lg:mb-4 font-bold">Previous Results</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="h-12 w-full bg-yellow-100 p-4 text-center">
        NOTE: The results presented on this page are not yet finalised and are subject to change pending verification.
    </div>

    {{-- MAIN CONTENT --}}
    <div class="mx-auto mt-12 sm:px-8 lg:px-12" id="public-map-app">
        <public-map></public-map>
    </div>

</div>
