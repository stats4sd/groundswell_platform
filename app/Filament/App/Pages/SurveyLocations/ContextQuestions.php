<?php

namespace App\Filament\App\Pages\SurveyLocations;

use App\Models\Team;
use Filament\Forms\Get;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use App\Services\HelperService;
use Filament\Actions\EditAction;
use Awcodes\TableRepeater\Header;
use Filament\Actions\StaticAction;
use Filament\Tables\Actions\Action;
use Filament\Support\Enums\MaxWidth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Actions\Contracts\HasActions;
use App\Filament\App\Pages\SurveyDashboard;
use Illuminate\Database\Eloquent\Collection;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Awcodes\TableRepeater\Components\TableRepeater;
use Filament\Actions\Concerns\InteractsWithActions;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use App\Filament\Shared\WithXlsformModuleVersionQuestionEditing;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;

class ContextQuestions extends Page implements HasActions, HasForms, HasTable
{

    use WithXlsformModuleVersionQuestionEditing;

    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.app.pages.survey-locations.context-questions';

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            SurveyLocationsIndex::getUrl() => 'Survey locations',
            static::getUrl() => static::getTitle(),
        ];
    }

    public Team $team;
    public XlsformModuleVersion $xlsformModuleVersion;

    // add variable $processing here to avoid program change in WithXlsformModuleVersionQuestionEditing.php (which should be commonly used by other programs)
    // $processing always have value 0, that means there is nothing being processed.
    public int $processing = 0;

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

    protected static bool $shouldRegisterNavigation = false;

    public function table(Table $table): Table
    {
        $locales = $this->team->locales;
        return $this->customModuleQuestionTable($table, $locales, $this->xlsformModuleVersion);
    }


}
