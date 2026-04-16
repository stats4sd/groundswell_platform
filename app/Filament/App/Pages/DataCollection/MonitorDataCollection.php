<?php

namespace App\Filament\App\Pages\DataCollection;

use App\Filament\App\Pages\SurveyDashboard;
use App\Livewire\SubmissionsTableView;
use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;

class MonitorDataCollection extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.app.pages.data-collection.monitor-data-collection';

    protected static bool $shouldRegisterNavigation = false;

    public Team $team;

    #[Url]
    public ?int $locationLevelId = null;

    #[Url]
    public ?string $table = null;

    #[Url]
    public ?array $tableFilters;

    public int $householdSubmissionCount;
    public int $fieldworkSubmissionCount;
    public int $completeFarmCount;
    public int $nonConsentingFarmCount;

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function mount(): void
    {
        /** @var Team team */
        $this->team = HelperService::getCurrentOwner();

        // summary

        $this->householdSubmissionCount = Submission::onlyRealData()
            ->whereHas('xlsformVersion', fn(Builder $query) => $query->whereHas('xlsform', fn(Builder $query) => $query->whereLike('title', '%HOLPA Household Form%')))->count();

        $this->fieldworkSubmissionCount = Submission::onlyRealData()
            ->whereHas('xlsformVersion', fn(Builder $query) => $query->whereHas('xlsform', fn(Builder $query) => $query->whereLike('title', '%HOLPA Fieldwork Form%')))->count();


        $this->completeFarmCount = $this->team->farms()
            ->where('household_form_completed', true)
            ->where('fieldwork_form_completed', true)
            ->count();

        $this->nonConsentingFarmCount = $this->team->farms()
            ->where('refused', true)
            ->count();
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            DatacollectionIndex::getUrl() => 'Data Collection',
            static::getUrl() => 'Monitor Data Collection',
        ];
    }

    public function showTable(string $table, ?int $locationLevelId = null)
    {
        $this->locationLevelId = $locationLevelId;
        $this->table = $table;
    }

}
