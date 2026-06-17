<?php

namespace App\Filament\App\Pages\DataAnalysis;

use App\Filament\Actions\ExportDataAction;
use App\Filament\App\Pages\SurveyDashboard;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

class DataAnalysisIndex extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string $view = 'filament.app.pages.data-analysis.data-analysis-index';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Data Analysis & Results';

    protected $listeners = ['refreshPage' => '$refresh'];

    public static function canAccess(): bool
    {
        return auth()->user()->can('view download data');
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            static::getUrl() => static::getTitle(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function exportDataAction(): Action
    {
        return ExportDataAction::make('exportData')
            ->label('Export Data')
            ->extraAttributes(['class' => 'buttona']);
    }


}
