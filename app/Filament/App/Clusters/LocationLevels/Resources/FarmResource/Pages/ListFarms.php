<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\FarmResource\Pages;

use App\Filament\App\Clusters\LocationLevels\Resources\FarmResource;
use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\App\Pages\SurveyLocations\SurveyLocationsIndex;
use App\Filament\Tables\Actions\ImportFarmsAction;
use App\Imports\FarmImport;
use App\Services\HelperService;
use Filament\Actions;
use Filament\Forms\Components\Wizard;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\Actions as ComponentActions;
use Filament\Forms\Components\Actions\Action as ComponentAction;
use Livewire\Attributes\On;

class ListFarms extends ListRecords
{
    protected static string $resource = FarmResource::class;

    protected ?string $heading = 'Survey locations';

    // protected ?string $subheading = 'List of farms';

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            SurveyLocationsIndex::getUrl() => 'Survey locations',
            static::getUrl() => static::getTitle(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FarmResource\Widgets\FarmListHeaderWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [

            // add a button to divert to import custom page
            Actions\Action::make('import')
                ->label('Import Locations and Farm List')
                ->extraAttributes(['class' => 'buttonb'])
                ->tooltip('Use this if you have your location and farm data all in one spreadsheet.')
                // disable if there is no location level with farms
                ->disabled(fn() => HelperService::getCurrentOwner()->locationLevels()->where('has_farms', 1)->count() < 1)
                ->url('farms/import'),

            ImportFarmsAction::make()
                ->color('primary')
                ->extraAttributes(['class' => 'buttonb'])
                ->tooltip('Use this if you have already added your locations')
                // disable if there is no location level with farms
                ->disabled(fn() => HelperService::getCurrentOwner()->locationLevels()->where('has_farms', 1)->count() < 1)
                ->use(FarmImport::class)
                ->label('Import Farm list'),
        ];
    }

    #[On('echo:xlsforms,FarmImportCompleted')]
    public function refreshTable(): void
    {
        $this->resetTable();
    }
}
