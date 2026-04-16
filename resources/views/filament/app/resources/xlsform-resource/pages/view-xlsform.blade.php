<x-filament-panels::page
    @class([ 'fi-resource-view-record-page' , 'fi-resource-' . str_replace('/', '-' , $this->getResource()::getSlug()),
    'fi-resource-record-' . $record->getKey(),
    ])
    >
    @php
    $relationManagers = $this->getRelationManagers();
    $hasCombinedRelationManagerTabsWithContent = $this->hasCombinedRelationManagerTabsWithContent();


    $summary = $this->getSummary();

    @endphp

    @if ((! $hasCombinedRelationManagerTabsWithContent) || (! count($relationManagers)))
    @if ($this->hasInfolist())
    {{ $this->infolist }}
    @else
    <div
        wire:key="{{ $this->getId() }}.forms.{{ $this->getFormStatePath() }}">
        {{ $this->form }}
    </div>
    @endif
    @endif

    <div class="grid grid-cols-2 gap-x-8">

        <x-filament::section>
            <x-slot name="heading">
                <b>Live Submissions</b>
            </x-slot>
            <p>
                <b>Number of Household Survey Submissions:</b> {{ $summary['householdCount'] }}
            </p>
            <p>
                <b>Number of Fieldwork Survey Submissions:</b> {{ $summary['fieldworkCount'] }}
            </p>
            <br />
            <p>
                <b>Number of Completed Surveys:</b> {{ $summary['successfulSurveys'] }}
                <br />
                <small>(Not including non-responses or non-consents)</small>
            </p>
            <br />
            <p>
                <b>Latest Household Submission:</b> {{ $summary['latestHouseholdSubmissionDate'] }}
            </p>
            <p>
                <b>Latest Fieldwork Submission:</b> {{ $summary['latestFieldworkSubmissionDate'] }}
            </p>
            <br />
            <p>
                <b>Submissions In Local Database:</b> {{ count($this->record->submissions) }}
                <br />
                <small>The Live Submissions count above is updated in real time. The number of submissions in the local database may not be fully up to date due to the time it takes to sync with the ODK System.</small>
            </p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                <b>Summary</b>
            </x-slot>

            <div class="grid grid-cols-2 gap-4">

                <b>Surveys Without Respondent Present</b>
                <span>{{ $summary['surveysWithoutRespondentPresent'] }}</span>

                <b>Surveys With Non-Consenting Respondent</b>
                <span>{{ $summary['surveysWithNonConsentingRespondent'] }}</span>

                <b>Farms Fully Surveyed</b>
                <span>{{ $summary['farmsFullySurvey'] }}</span>

            </div>
        </x-filament::section>
    </div>


    @if (count($relationManagers))
    <x-filament-panels::resources.relation-managers
        :active-locale="isset($activeLocale) ? $activeLocale : null"
        :active-manager="$this->activeRelationManager ?? ($hasCombinedRelationManagerTabsWithContent ? null : array_key_first($relationManagers))"
        :content-tab-label="$this->getContentTabLabel()"
        :content-tab-icon="$this->getContentTabIcon()"
        :content-tab-position="$this->getContentTabPosition()"
        :managers="$relationManagers"
        :owner-record="$record"
        :page-class="static::class">
        @if ($hasCombinedRelationManagerTabsWithContent)
        <x-slot name="content">
            @if ($this->hasInfolist())
            {{ $this->infolist }}
            @else
            {{ $this->form }}
            @endif
        </x-slot>
        @endif
    </x-filament-panels::resources.relation-managers>
    @endif
</x-filament-panels::page>
