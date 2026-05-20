<?php

namespace App\Filament\App\Pages\SurveyLocations;

use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\Shared\WithXlsformModuleVersionQuestionEditing;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class ContextQuestions extends Page implements HasActions, HasForms, HasTable
{
    use WithXlsformModuleVersionQuestionEditing;
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.app.pages.survey-locations.context-questions';

    protected static bool $shouldRegisterNavigation = false;

    public Team $team;
    public XlsformModuleVersion $xlsformModuleVersion;

    // $processing always has value 0 — nothing is being processed at this level.
    // Declared here to avoid modifying the shared trait.
    public int $processing = 0;

    public static function canAccess(): bool
    {
        return auth()->user()->can('view context questions');
    }

    public function mount(): void
    {
        $team = HelperService::getCurrentOwner();

        if ($team === null) {
            abort(404);
        }

        $this->team = $team;

        $this->xlsformModuleVersion = $this->team->localContextModuleVersion->load(['surveyRows.languageStrings', 'surveyRows.choiceList.choiceListEntries.languageStrings']);

        $this->form->fill($this->xlsformModuleVersion->toArray());
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            SurveyLocationsIndex::getUrl() => 'Survey locations',
            static::getUrl() => static::getTitle(),
        ];
    }

    public function table(Table $table): Table
    {
        $locales = $this->team->locales;
        $table = $this->customModuleQuestionTable($table, $locales, $this->xlsformModuleVersion);

        if (!auth()->user()->can('maintain context questions')) {
            return $table->headerActions([])->actions([])->reorderable(null);
        }

        return $table;
    }
}
