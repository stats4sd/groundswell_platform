<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource\Pages;

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

    protected ?string $heading = 'Survey locations';

    public function getSubheading(): string|Htmlable
    {
        return 'List of '.Str::of($this->record->name)->plural()->title();
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            SurveyLocationsIndex::getUrl() => 'Survey locations',
            route('filament.app.location-levels.resources.location-levels.view', [
                'tenant' => HelperService::getCurrentOwner()->id,
                'record' => $this->record->slug,
            ]) => Str::of($this->record->name)->plural(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ImportLocationsAction::make()
                ->use(LocationImport::class)
                ->color('primary')
                ->label('Import '.Str::of($this->record->name)->plural()),
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
