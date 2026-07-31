<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Pages;

use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource;
use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Widgets\FarmListHeaderWidget;
use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\App\Pages\SurveyLocations\SurveyLocationsIndex;
use App\Filament\Tables\Actions\ImportFarmsAction;
use App\Imports\FarmEntityImport;
use App\Services\HelperService;
use App\Services\OdkFarmEntityService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListFarmEntities extends ListRecords
{
    protected static string $resource = FarmEntityResource::class;

    public function getHeading(): string
    {
        return t('Survey locations');
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
            SurveyLocationsIndex::getUrl() => t('Survey locations'),
            static::getUrl() => t('Farms'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FarmListHeaderWidget::class,
        ];
    }

    /**
     * The live feed fetched once per page load - keyed by odk_uuid, each entry
     * ['label' => ..., 'data' => [propertyName => value]]. Read by the table's dynamic
     * property columns (see FarmEntityResource::table()) via Filament's $livewire closure
     * injection - nothing about a farm's values is persisted locally.
     */
    public array $liveFarmData = [];

    public function mount(): void
    {
        parent::mount();

        $this->liveFarmData = app(OdkFarmEntityService::class)->refreshFromCentral(HelperService::getCurrentOwner());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label(fn () => t('Import Locations and Farm List'))
                ->extraAttributes(['class' => 'buttonb'])
                ->tooltip(fn () => t('Use this if you have your location and farm data all in one spreadsheet.'))
                ->visible(fn () => auth()->user()->can('maintain list of farms'))
                ->disabled(fn () => HelperService::getCurrentOwner()->locationLevels()->where('has_farms', 1)->count() < 1)
                ->url(fn () => FarmEntityResource::getUrl('import')),

            // Reuses the existing column-mapping modal as-is (it's about parsing a
            // spreadsheet, independent of storage backend) - only the underlying import
            // class differs.
            ImportFarmsAction::make()
                ->color('primary')
                ->extraAttributes(['class' => 'buttonb'])
                ->tooltip(fn () => t('Use this if you have already added your locations'))
                ->visible(fn () => auth()->user()->can('maintain list of farms'))
                ->disabled(fn () => HelperService::getCurrentOwner()->locationLevels()->where('has_farms', 1)->count() < 1)
                ->use(FarmEntityImport::class)
                ->label(fn () => t('Import Farm list')),
        ];
    }
}
