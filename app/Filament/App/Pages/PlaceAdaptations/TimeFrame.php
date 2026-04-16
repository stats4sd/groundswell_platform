<?php

namespace App\Filament\App\Pages\PlaceAdaptations;

use App\Filament\App\Pages\SurveyDashboard;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;

class TimeFrame extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.app.pages.place-adaptations.time-frame';

    protected ?string $heading = 'Survey Testing - Adapt Time Frame';

    // protected ?string $subheading = 'Specify the time frame for your survey';

    public ?array $data = [];

    public bool $showCompiledText = false;

    public Team $team;

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
        return $form
            ->statePath('data')
            ->model($this->team)
            ->schema([
                TextInput::make('time_frame')
                    ->live()
                    ->afterStateUpdated(fn(self $livewire) => $livewire->saveData()),
            ]);
    }

    public function saveData(): void
    {

        $this->team->update($this->form->getState());

        Notification::make('updated')
            ->success()
            ->title('Time Frame Updated Successfully')
            ->send();
    }

    public function table(Table $table): Table
    {

        return $table
            ->heading('Questions that reference the time frame')
            ->description(fn(self $livewire) => $livewire->showCompiledText
                ? 'Showing the text as it will appear to enumerators'
                : 'Showing the text including the ODK form ${time_frame} variable placeholder'
            )
            ->headerActions([
                Action::make('toggle_text')
                    ->label(fn(self $livewire) => $livewire->showCompiledText ? 'Show ODK Variable in labels' : 'Show final question text')
                    ->action(fn(self $livewire) => $livewire->showCompiledText = !$livewire->showCompiledText),
            ])
            ->defaultPaginationPageOption(50)
            ->query(
                fn() => SurveyRow::query()
                    ->whereHas('xlsformModuleVersion', fn($query) => $query->where('is_default', 1))
                    ->whereHas('languageStrings', fn($query) => $query->whereLike('text', '%${time_frame}%'))
            )
            ->columns([
                TextColumn::make('name')->label('Name'),
                TextColumn::make('defaultLabel.text')->label('Label (en)')->wrap()
                    ->formatStateUsing(fn(?string $state, self $livewire) => $livewire->showCompiledText
                        ? new HtmlString(Str::replace('${time_frame}', '<b>'.$livewire->team->time_frame.'</b>', $state))
                        : new HtmlString(Str::replace('${time_frame}', '<b>${time_frame}</b>', $state))
                    ),
                TextColumn::make('defaultHint.text')->label('Hint (en)')->wrap()
                    ->formatStateUsing(fn(?string $state, self $livewire) => $livewire->showCompiledText ? Str::replace('${time_frame}', $livewire->team->time_frame, $state) : $state),
            ]);
    }
}
