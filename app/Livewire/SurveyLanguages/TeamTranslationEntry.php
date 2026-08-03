<?php

namespace App\Livewire\SurveyLanguages;

use App\Models\Team;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Application;
use Livewire\Attributes\On;
use Livewire\Component;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;

class TeamTranslationEntry extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public Team $team;

    public Language $language;

    public ?Locale $selectedLocale = null;

    public bool $expanded;

    public function mount(): void
    {
        $this->selectedLocale = Locale::find($this->language->pivot->locale_id);
    }

    public function render(): Factory|Application|View|\Illuminate\View\View|null
    {
        return view('livewire.survey-languages.team-translation-entry');
    }

    public function table(Table $table): Table
    {
        return $table
            ->relationship(
                fn () => $this->language
                    ->locales()
                    ->where(fn (Builder $query) => $query
                        ->where('is_default', true)
                        ->orWhere('creator_id', $this->team->id)
                    )
            )
            ->recordClasses(fn (Locale $record) => $record->id === $this->selectedLocale?->id ? 'success-row' : '')
            ->columns([

                // add icon to indicate translation label can be edited
                TextColumn::make('language_label')->label(fn () => t('Available Translations'))
                    // do not show icon for default locale, to indicate it cannot be edited (even it is still clickable...)
                    ->icon(fn (Locale $record) => $record->is_default == 1 ? '' : 'heroicon-o-pencil-square')
                    ->iconColor(fn (Locale $record) => $record->is_default == 1 ? 'grey' : 'primary')
                    // show underline when user move mouse over the column, to indicate user can click on it
                    ->tooltip(fn (Locale $record) => $record->is_default == 1 ? '' : t('Click to update this translation label'))
                    ->extraCellAttributes(fn (Locale $record) => $record->is_default == 1 ? [] : ['class' => 'hover:underline'])
                    ->action(
                        Action::make('edit_label')
                            // the disabled() helper function helps to not showing the modal popup for the default locale
                            ->disabled(fn (Locale $record) => $record->is_default == 1)

                            ->modalHeading(fn (Locale $record) => t('Update Translation Label for').' '.$record->description)
                            ->schema([
                                TextInput::make('description')
                                    ->label(fn () => t('Enter a new label for the translation'))
                                    ->helperText(fn () => t('E.g. "Portuguese (Brazil)"')),
                            ])
                            ->action(function (array $data, Locale $record): void {
                                $record->description = $data['description'];
                                $record->save();
                            }),
                    ),

                TextColumn::make('status')
                    ->label(fn () => t('Status'))
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'Ready for use' => t('Ready for use'),
                        'Not uploaded' => t('Not uploaded'),
                        'Translations incomplete' => t('Translations incomplete'),
                        'Needs updating' => t('Needs updating'),
                        default => $state,
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading(fn () => t('No translations available.'))
            ->heading('')
            ->headerActions([
                Action::make('Add New')
                    ->label(fn () => t('Add new'))
                    ->extraAttributes(['class' => 'buttonb my-4 shadow-none'])
                    ->icon('heroicon-o-plus-circle')
                    ->visible(fn () => auth()->user()->can('maintain survey translations'))
                    ->schema([
                        TextInput::make('description')
                            ->label(t('Enter a label for the translation'))
                            ->helperText(t('E.g. "Portuguese (Brazil)"'))
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        if (! auth()->user()->can('maintain survey translations')) {
                            abort(403);
                        }

                        $this->language->locales()->create([
                            'description' => $data['description'],
                            'creator_id' => $this->team->id,
                        ]);
                    }),
            ])
            ->recordActions([
                Action::make('Select')
                    ->extraAttributes(['class' => ' mx-auto'])
                    ->icon(fn (Locale $record) => $record->id === $this->selectedLocale?->id ? 'heroicon-o-check-circle' : '')
                    ->color(fn (Locale $record) => $record->id === $this->selectedLocale?->id ? 'success' : 'primary')
                    ->label(fn (Locale $record) => $record->id === $this->selectedLocale?->id ? t('Selected') : t('Select'))
                    ->disabled(fn (Locale $record) => $this->selectedLocale?->id === $record->id)
                    ->tooltip(fn () => t('Select this translation for your survey'))
                    ->action(function (Locale $record) {
                        $record->language->owners()->updateExistingPivot($this->team->id, ['locale_id' => $record->id]);
                        $this->selectedLocale = $record;
                    }),

                Action::make('view-edit')
                    ->extraAttributes(['class' => 'ml-2 buttona translations_viewedit'])
                    ->color('white')
                    ->label(fn () => t('View / Edit Translation'))
                    ->modalHeading(fn (Locale $record) => t('View / Edit Translation for').' '.$record->language_label)
                    ->modalContent(fn (Locale $record) => view('team-translation-review', [
                        'locale' => $record,
                        'team' => $this->team,
                        'canMaintain' => auth()->user()->can('maintain survey translations'),
                    ]))
                    ->modalWidth(Width::SixExtraLarge)
                    ->extraModalWindowAttributes(['class' => 'py-4 px-10'])
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false),

            ]);
    }

    #[On('closeModal')]
    public function closeModal() {}

    #[On('echo:xlsforms,LanguageImportIsComplete')]
    public function refreshLocales(): void
    {

        $this->resetTable();
    }
}
