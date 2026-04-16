<div class="actionblock pl-0">
    <div class="flex">
        <div class="bg-green " style="border-top-right-radius: 2.2rem; border-bottom-right-radius: 2.2rem; padding-left: 1rem; padding-right: 1rem; height: 7rem; place-content: center; margin-right: 4rem">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.0" stroke="white" class="" style="height: 2.5rem">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672 13.684 16.6m0 0-2.51 2.225.569-9.47 5.227 7.917-3.286-.672ZM12 2.25V4.5m5.834.166-1.591 1.591M20.25 10.5H18M7.757 14.743l-1.59 1.59M6 10.5H3.75m4.007-4.243-1.59-1.59"/>
            </svg>
        </div>
        <div class="w-1/2 xl:w-2/3 flex-shrink" style=" place-content: center;">
            <!-- Heading -->
            <h3 class="">
                {{ $heading }}
            </h3>

            <div class="flex justify-between items-center mt-2 ">
                <!-- Description -->
                <p class="">
                    {!! $description !!}
                </p>
            </div>
        </div>
        <div class="mx-auto" style="place-content: center;">
            <!-- Button (Right) -->
            @if(isset($actionButton))
                {{ $actionButton }}
            @else
                <a href="{{ $url }}"
                   class="buttona ml-4">
                    {{ $buttonLabel }}
                </a>
            @endif
        </div>
    </div>
    <div class="border-b my-4 actionblockborder "></div>
</div>
