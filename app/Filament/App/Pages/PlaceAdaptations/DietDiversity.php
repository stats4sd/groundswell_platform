<?php

namespace App\Filament\App\Pages\PlaceAdaptations;

use App\Filament\App\Pages\SurveyDashboard;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class DietDiversity extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public ?array $data = [];

    public Team $team;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.app.pages.place-adaptations.diet-diversity';

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            PlaceAdaptationsIndex::getUrl() => 'Place Adaptations',
            static::getUrl() => static::getTitle(),
        ];
    }

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();
        $this->form->fill($this->team->toArray());
    }

    public function form(Form $form): Form
    {
        // If the team's `diet_diversity_module_version_id` is null, set the default using the team's country
        if (is_null($this->team->diet_diversity_module_version_id)) {
            $this->team->diet_diversity_module_version_id = XlsformModuleVersion::query()
                ->whereHas('xlsformModule', fn ($query) => $query->where('name', 'diet_quality'))
                ->where('country_id', $this->team->country_id)
                ->value('id');

            $this->team->save();
        }

        return $form
            ->statePath('data')
            ->model($this->team)
            ->schema([
                Select::make('diet_diversity_module_version_id')
                    ->live()
                    ->relationship(
                        'dietDiversityModuleVersion',
                        'name',
                        fn ($query) => $query
                            ->where('is_default', 0)
                            ->whereHas('xlsformModule', fn ($query) => $query->where('name', 'diet_diversity'))
                    )
                    ->afterStateUpdated(fn (self $livewire) => $livewire->saveData()),
            ]);
    }

    public function saveData(): void
    {

        $this->team->update($this->form->getState());

        $this->resetTable();

        Notification::make('updated')
            ->success()
            ->title('Diet Diversity Module Selected Successfully')
            ->send();
    }

    public function table(Table $table): Table
    {
        if ($this->team->diet_diversity_module_version_id) {
            $moduleVersion = $this->team->dietDiversityModuleVersion;
        } else {
            //  default to the 'default' diet diversity module version;
            $moduleVersion = XlsformModuleVersion::where('is_default', 1)
                ->whereHas('xlsformModule', fn ($query) => $query->where('name', 'diet_diversity'))
                ->first();
        }

        return $table
            ->query(
                fn () => $moduleVersion->surveyRows()
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('type')->label('Question Type'),
                TextColumn::make('name')->label('Name'),
                TextColumn::make('defaultLabel.text')->label('Label (en)')->wrap(),
                TextColumn::make('defaultHint.text')->label('Hint (en)')->wrap()
                ->width('40%'),
            ]);
    }
}
