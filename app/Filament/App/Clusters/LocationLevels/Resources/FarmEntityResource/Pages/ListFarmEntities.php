<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Pages;

use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource;
use App\Filament\Tables\Actions\ImportFarmsAction;
use App\Imports\FarmEntityImport;
use App\Services\HelperService;
use App\Services\OdkFarmEntityService;
use Filament\Resources\Pages\ListRecords;

class ListFarmEntities extends ListRecords
{
    protected static string $resource = FarmEntityResource::class;

    public function getHeading(): string
    {
        return t('Farms (ODK Entities - preview)');
    }

    // Read-through refresh: pull the current state from ODK Central's live feed once per
    // page load, rather than trusting whatever is already in the local EntityValue rows.
    public function mount(): void
    {
        parent::mount();

        app(OdkFarmEntityService::class)->refreshFromCentral(HelperService::getCurrentOwner());
    }

    protected function getHeaderActions(): array
    {
        return [
            // Reuses the existing column-mapping modal as-is (it's about parsing a
            // spreadsheet, independent of storage backend) - only the underlying import
            // class differs. NOTE: the Import audit record this creates is tagged
            // model_type => Farm::class regardless (hardcoded in ImportFarmsAction), which
            // is cosmetically inaccurate for entity imports but doesn't affect behaviour.
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
