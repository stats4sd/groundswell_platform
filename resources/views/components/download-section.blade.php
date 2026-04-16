<div class=" xl:w-[80%]  pb-12">
    <div class="flex flex-col md:flex-row h-full items-center" >
    <div class="bg-blue rounded-full mx-auto mb-4 md:m-0 md:mr-2 hidden md:flex items-center" style="height: 4rem; width: 4rem; ; place-content: center; ">


        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="white" class="mx-auto " style="height: 2rem;">
  <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
</svg>

    </div>
    <div class="mx-auto w-full md:w-[50%] lg:w-[60%]"  style=" place-content: center;  ">
        <!-- Heading -->
        <h4 class=" text-blue">
            {{ $heading }}
        </h4>

        <div class="flex justify-between items-center mt-2   ">
            <!-- Description -->
            <p class="">
                {!! $description !!}
            </p>
        </div>
    </div>
    <div style="place-content: center; " class="mx-auto mt-6 mb-18 md:my-0">
        <!-- Button (Right) -->
        <a href="{{ $url }}"
           class="buttona min-w-max ml-4">
            {{ $buttonLabel }}
        </a>
        </div>
    </div>

</div>
