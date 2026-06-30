<?php

namespace App\Filament\App\Pages\DataAnalysis;

use Filament\Support\Enums\Width;
use App\Filament\Actions\ExportDataAction;
use App\Filament\App\Pages\SurveyDashboard;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class DataAnalysisIndex extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.app.pages.data-analysis.data-analysis-index';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return t('Data Analysis & Results');
    }

    protected $listeners = ['refreshPage' => '$refresh'];

    public static function canAccess(): bool
    {
        return auth()->user()->can('view download data');
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
            static::getUrl() => static::getTitle(),
        ];
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function exportDataAction(): Action
    {
        return ExportDataAction::make('exportData')
            ->label(fn () => t('Export Data'))
            ->extraAttributes(['class' => 'buttona']);
    }


}
