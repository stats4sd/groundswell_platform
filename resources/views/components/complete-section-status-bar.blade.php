@props([
    'surveyDashboardUrl',
    /** @var \App\Filament\App\Pages\SurveyLanguages\SurveyLanguagesIndex */
    'this'
])

<div {{ $attributes->class(['completebar']) }}>
    @if(\App\Services\HelperService::getCurrentOwner()->{$completionProp})
        <div class="mb-6 mx-auto md:mr-24 md:ml-0 md:inline-block block text-green ">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 inline " fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <span class="ml-1 inline text-sm font-bold">SECTION COMPLETE </span>
        </div>
        <a href="{{ \App\Filament\App\Pages\SurveyDashboard::getUrl() }}" class="buttonb block max-w-sm mx-auto md:mx-4 md:inline-block mb-6 md:mb-0">Go back</a>
        {!! $markIncompleteAction !!}
    @else
        <a href="{{ \App\Filament\App\Pages\SurveyDashboard::getUrl() }}" class="buttonb mx-4 inline-block">Go back</a>
        {!! $markCompleteAction !!}
    @endif
</div>
