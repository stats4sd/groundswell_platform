<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource\Pages;

use App\Filament\App\Clusters\LocationLevels\Resources\ImportResource\Widgets\RecentImportsWidget;
use App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource;
use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\App\Pages\SurveyLocations\SurveyLocationsIndex;
use App\Filament\Tables\Actions\ImportLocationsAction;
use App\Imports\LocationImport;
use App\Services\HelperService;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

class ViewLocationLevel extends ViewRecord
{
    protected static string $resource = LocationLevelResource::class;

    public function getHeading(): string
    {
        return t('Survey locations');
    }

    public function getSubheading(): string|Htmlable
    {
        return t('List of').' '.Str::of($this->record->name)->plural()->title();
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
            SurveyLocationsIndex::getUrl() => t('Survey locations'),
            route('filament.app.location-levels.resources.location-levels.view', [
                'tenant' => HelperService::getCurrentOwner()->id,
                'record' => $this->record->slug,
            ]) => Str::of($this->record->name)->plural(),
        ];
    }

    // the standalone location import closes its modal and says nothing at all, so without this
    // there is no surface on this page reporting what became of the file
    protected function getHeaderWidgets(): array
    {
        return [
            RecentImportsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ImportLocationsAction::make()
                ->use(LocationImport::class)
                ->color('primary')
                ->label(t('Import').' '.Str::of($this->record->name)->plural()),
        ];
    }

    public function getSubNavigation(): array
    {
        if (filled($cluster = static::getCluster())) {
            return $this->generateNavigationItems($cluster::getClusteredComponents());
        }

        return [];
    }
}
