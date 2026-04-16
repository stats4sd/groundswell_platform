<div {{ $attributes->class(['md:mx-16 bg-red-100 border-2 border-red-700 text-red-700 px-4 py-3 rounded-xl relative flex flex-col md:flex-row items-center']) }} role="alert">

    <x-heroicon-o-exclamation-triangle class="w-16 sm:w-20 flex-shrink-0 text-red mb-2 md:mb-0"/>
    <div class="md:ml-8 py-auto">
        {!! $content !!}

    </div>
</div>
