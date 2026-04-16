<?php

namespace App\Livewire\SurveyLanguages;

use App\Models\Team;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Stats4sd\FilamentOdkLink\Exports\XlsformTemplateTranslationsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class TeamTranslationEntry extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public Team $team;

    public Language $language;

    public ?Locale $selectedLocale = null;

    public bool $expanded;

    public function mount(Locale $locale): void
    {
        $this->selectedLocale = Locale::find($this->language->pivot->locale_id);
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\View|\Illuminate\View\View|null
    {
        return view('livewire.survey-languages.team-translation-entry');
    }

    public function table(Table $table): Table
    {
        return $table
            ->relationship(
                fn () => $this->language
                    ->locales()
            )
            ->recordClasses(fn (Locale $record) => $record->id === $this->selectedLocale?->id ? 'success-row' : '')
            ->columns([

                // add icon to indicate translation label can be edited
                TextColumn::make('language_label')->label('Available Translations')
                    // do not show icon for default locale, to indicate it cannot be edited (even it is still clickable...)
                    ->icon(fn (Locale $record) => $record->is_default == 1 ? '' : 'heroicon-o-pencil-square')
                    ->iconColor(fn (Locale $record) => $record->is_default == 1 ? 'grey' : 'primary')
                    // show underline when user move mouse over the column, to indicate user can click on it
                    ->tooltip(fn (Locale $record) => $record->is_default == 1 ? '' : 'Click to update this translation label')
                    ->extraCellAttributes(fn (Locale $record) => $record->is_default == 1 ? [] : ['class' => 'hover:underline'])
                    ->action(
                        Action::make('edit_label')
                            // the disabled() helper function helps to not showing the modal popup for the default locale
                            ->disabled(fn (Locale $record) => $record->is_default == 1)

                            ->modalHeading(fn (Locale $record) => 'Update Translation Label for '.$record->description)
                            ->form([
                                TextInput::make('description')
                                    ->label('Enter a new label for the translation')
                                    ->helperText('E.g. "Portuguese (Brazil)"'),
                            ])
                            ->action(function (array $data, Locale $record): void {
                                $record->description = $data['description'];
                                $record->save();
                            }),
                    ),

                TextColumn::make('status')->label('Status')
            ])
            ->paginated(false)
            ->emptyStateHeading('No translations available.')
            ->heading('')
            ->headerActions([
                Action::make('Add New')
                    ->extraAttributes(['class' => 'buttonb my-4 shadow-none !py-21'])
                    ->icon('heroicon-o-plus-circle')
                    ->form([
                        TextInput::make('description')
                            ->label('Enter a label for the translation')
                            ->helperText('E.g. "Portuguese (Brazil)"')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $this->language->locales()->create([
                            'description' => $data['description'],
                            'creator_id' => $this->team->id,
                        ]);
                    }),
            ])
            ->actions([
                Action::make('Select')
                    ->extraAttributes(['class' => ' mx-auto'])
                    ->icon(fn (Locale $record) => $record->id === $this->selectedLocale?->id ? 'heroicon-o-check-circle' : '')
                    ->color(fn (Locale $record) => $record->id === $this->selectedLocale?->id ? 'success' : 'primary')
                    ->label(fn (Locale $record) => $record->id === $this->selectedLocale?->id ? 'Selected' : 'Select')
                    ->disabled(fn (Locale $record) => $this->selectedLocale?->id === $record->id)
                    ->tooltip('Select this translation for your survey')
                    ->action(function (Locale $record) {
                        $record->language->owners()->updateExistingPivot($this->team->id, ['locale_id' => $record->id]);
                        $this->selectedLocale = $record;
                    }),

                Action::make('view-edit')
                    ->extraAttributes(['class' => 'ml-2 buttona translations_viewedit'])
                    ->color('white')
                    ->label('View / Edit Translation')
                    ->modalHeading(fn (Locale $record) => 'View / Edit Translation for '.$record->language_label)
                    ->modalContent(fn (Locale $record) => view('team-translation-review', ['locale' => $record, 'team' => $this->team]))
                    ->modalWidth(MaxWidth::SixExtraLarge)
                    ->extraModalWindowAttributes(['class' => 'py-4 px-10'])
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false),

            ]);
    }

    #[On('closeModal')]
    public function closeModal() {}

    public function validateFileUpload(array $upload, Locale $record, XlsformTemplate $xlsformTemplate): \Closure
    {
        return function (string $attribute, string $value, \Closure $fail) use ($upload, $record, $xlsformTemplate) {

            $file = collect($upload)->first();

            /** @var Collection $rows */
            $rows = Excel::toCollection((object) [], $file)[0];

            $headers = $rows->shift();

            // check the required columns exist
            $rowTypeIndex = $headers->contains('type');
            $nameIndex = $headers->contains('name');
            $stringTypeIndex = $headers->contains('translation type');
            $currentTranslationIndex = $headers->contains($record->language_label);

            if ($currentTranslationIndex === false || $nameIndex === false || $stringTypeIndex === false) {

                $missingColumns = collect([
                    $nameIndex ? '' : 'name',
                    $stringTypeIndex ? '' : 'translation type',
                    $currentTranslationIndex ? '' : $record->language_label,
                ]);

                return $fail("The uploaded file is missing the following required columns: {$missingColumns->join(', ')}. Please check that you are using the template downloaded from this platform, and please do not edit the column headers.");
            }

            // check that all the required names are present
            $processor = new XlsformTemplateTranslationsExport($xlsformTemplate, $record);

            $templateSurveyRows = $xlsformTemplate->surveyRows
                ->map(fn (SurveyRow $entry) => $processor->processEntry($entry));
            $templateChoiceListEntries = $xlsformTemplate->choiceListEntries
                ->map(fn (ChoiceListEntry $entry) => $processor->processEntry($entry));

            // make sure all template survey rows are present in the $rows collection
            $missingSurveyRows = $templateSurveyRows
                ->filter(fn ($entry) => $rows->doesntcontain($entry, '=', 'name'))
                ->filter(fn ($entry) => $rows->doesntcontain($entry, '=', 'translation_type'));

            // make sure all template choice list rows are present in the $rows collection
            $missingChoiceListEntries = $templateChoiceListEntries
                ->filter(fn ($entry) => $rows->doesntcontain($entry, '=', 'name'))
                ->filter(fn ($entry) => $rows->doesntcontain($entry, '=', 'translation_type'));

            // TODO: finish validating that all required rows are present;
            // TODO: validate that all translation_types are valid language string types.

            return true;
        };
    }

    #[On('echo:xlsforms,LanguageImportIsComplete')]
    public function refreshLocales(): void
    {

    $this->resetTable();
    }
}
