@props([
  'actions' => [],
  'breadcrumbs' => [],
  'heading' => '',
  'subheading' => null,
  'summary' => null,
])

<div class="min-h-60 w-screen full-width -mt-8 bg-white shadow-md pb-10 pt-10 px-8 md:px-12 lg:px-20">
    <x-filament::breadcrumbs
        :breadcrumbs="$breadcrumbs"
        class="mb-8 hidden sm:block"
    />
    <h1 class="fi-header-heading text-2xl font-bold dark:text-white sm:text-4xl">{{ $heading }}</h1>
    @if($summary)
        <p class="my-4   w-3/4 text-base font-light">{!! $summary !!}</p>
    @endif
</div>
