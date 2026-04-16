<?php

namespace App\Filament\Shared;

use App\Services\HelperService;
use Filament\Actions\StaticAction;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

trait WithXlsformModuleVersionQuestionEditing
{

    /**
     *
     * Returns a table of SurveyRow entries linked to the given XlsformModuleVersion. Create + Edit questions are enabled for a "simplified" way of editing the content of the module (only basic Xlsform columns enabled, no calculations, no relevancy / validation code...). Locales required to know how many languageStrings are required.
     *
     * @param Table $table
     * @param Collection<Locale> $locales
     * @param XlsformModuleVersion $xlsformModuleVersion
     */
    private function customModuleQuestionTable(Table $table, Collection $locales, XlsformModuleVersion $xlsformModuleVersion): Table
    {
        // for new questions, set the default languageStrings to 'label' + 'hint' - one for each of the team locales.
        $defaultLanguageStringState = $locales->map(function (Locale $locale) {
            return [
                [
                    'locale_id' => $locale->id,
                    'language_string_type_id' => LanguageStringType::where('name', 'label')->first()->id,
                    'text' => '',
                ],
                [
                    'locale_id' => $locale->id,
                    'language_string_type_id' => LanguageStringType::where('name', 'hint')->first()->id,
                    'text' => '',
                ],
            ];
        })->flatten(1)->toArray();

        $defaultChoiceLanguageStringState = $locales->map(function (Locale $locale) {
            return [
                'locale_id' => $locale->id,
                'language_string_type_id' => LanguageStringType::where('name', 'label')->first()->id,
                'text' => '',
            ];
        })->toArray();

        $questionForm = [
            Hidden::make('xlsform_module_version_id')->default($xlsformModuleVersion->id)->live(),
            Hidden::make('id'),
            Fieldset::make('Question Information')
                ->columns([
                    'sm' => 1,
                    'md' => 2,
                    'lg' => 2,
                ])
                ->schema([
                    Hidden::make('row_number')
                        ->default(function () use ($xlsformModuleVersion) {
                            // find the largest row_number of survey_rows records
                            $number = $xlsformModuleVersion->surveyRows->last()->row_number ?? 0;
                            return $number + 1;
                        }),
                    Select::make('type')->options([
                        'select_one' => 'select_one',
                        'select_multiple' => 'select_multiple',
                        'decimal' => 'decimal',
                        'integer' => 'integer',
                        'text' => 'text',
                    ])
                        ->afterStateHydrated(function (Select $component, ?string $state) {
                            if (Str::startsWith($state, 'select_one')) {
                                $component->state('select_one');
                            }

                            if (Str::startsWith($state, 'select_multiple')) {
                                $component->state('select_multiple');
                            }
                        })
                        ->dehydrateStateUsing(function ($state, Get $get) {
                            if ($state === 'select_one' || $state === 'select_multiple') {
                                $state = $state . ' ' . Str::lower(Str::slug($get('name'), separator: '_'));
                            }

                            return $state;
                        })
                        ->live(onBlur: true)
                        ->required(),
                    TextInput::make('name')->label('Variable Name')->live()->required()
                        ->helperText('The variable name should only have alphanumeric characters or underscores. Any spaces will be automatically replaced with underscores when saving this question.')
                        ->dehydrateStateUsing(fn($state): string => Str::lower(Str::slug($state, separator: '_'))),
                    Repeater::make('languageStrings')
                        ->extraAttributes(['class' => 'inline-repeater'])
                        ->label('')
                        ->columnSpan(2)
                        ->addable(false)
                        ->deletable(false)
                        ->grid(2)
                        ->relationship('languageStrings')
                        ->saveRelationshipsWhenHidden(true)
                        ->schema([
                            Hidden::make('locale_id'),
                            Hidden::make('language_string_type_id'),

                            TextInput::make('text')
                                ->required(fn(Get $get) => LanguageStringType::find($get('language_string_type_id'))->name === 'label')
                                ->label(fn(Get $get) => LanguageStringType::find($get('language_string_type_id'))->name . ' - ' . Locale::find($get('locale_id'))->language_label),
                        ])
                        ->default($defaultLanguageStringState)
                        ->live(onBlur: true),

                ])->live(onBlur: true),
            Fieldset::make('Choice List')
                ->columns([
                    'sm' => 1,
                    'md' => 1,
                    'lg' => 1,
                ])
                ->live(onBlur: true)
                ->visible(fn(Get $get) => Str::startsWith($get('type'), 'select_one') || Str::startsWith($get('type'), 'select_multiple'))
                ->relationship('choiceList')
                ->saveRelationshipsBeforeChildrenUsing(fn(Fieldset $component) => $component->saveRelationships())
                ->schema([
                    Hidden::make('xlsform_module_version_id')->default($xlsformModuleVersion->id)->live(),
                    Hidden::make('list_name')->live(onBlur: true)
                        ->dehydrateStateUsing(fn(Get $get) => $get('../name') . '_choices_' . Str::random(8)),
                    Repeater::make('choiceListEntries')
                        ->relationship('choiceListEntries')
                        ->label('Options list for the select question')
                        ->itemLabel(fn(array $state): ?string => $state['name'] ?? null)
                        ->schema([
                            TextInput::make('name')
                                ->label('Option name')
                                ->helperText('This is the "name" column in ODK, and is the value that appears in the data when the option is selected')
                                ->required(),
                            Hidden::make('owner_id')->default(HelperService::getCurrentOwner()->id),
                            Repeater::make('languageStrings')
                                ->extraAttributes(['class' => 'inline-repeater'])
                                ->columnSpan(2)
                                ->grid(2)
                                ->label('')
                                ->addable(false)
                                ->deletable(false)
                                ->relationship('languageStrings')
                                ->saveRelationshipsWhenHidden(true)
                                ->schema([
                                    Hidden::make('locale_id'),
                                    Hidden::make('language_string_type_id'),

                                    TextInput::make('text')
                                        ->required()
                                        ->label(fn(Get $get) => LanguageStringType::find($get('language_string_type_id'))->name . ' - ' . Locale::find($get('locale_id'))->language_label),
                                ])
                                ->default($defaultChoiceLanguageStringState)
                                ->live(onBlur: true),
                        ]),
                ]),

        ];

        return $table
            ->query(
                fn() => SurveyRow::query()
                    ->where('xlsform_module_version_id', $xlsformModuleVersion->id)
                    // sort record by row_number, to reflect the user defined ordering by drag and drop
                    ->orderBy('row_number'),
            )
            ->columns([
                TextColumn::make('type')->label('Question Type'),
                TextColumn::make('name')->label('Variable Name')->wrap(),
                // fix to show default label as string instead of a JSON array
                TextColumn::make('defaultLabel.text')->label('Default Label'),
            ])
            // allow user to change the ordering by drag and drop
            ->reorderable('row_number')
            ->reorderRecordsTriggerAction(
                fn(Action $action, bool $isReordering) => $action
                    ->button()
                    ->label($isReordering ? 'Disable reordering' : 'Enable reordering')
                    ->extraAttributes(['class' => 'buttona border-0 !ring-0 !shadow-none -ml-2  mb-4'])
                    ->icon(fn(self $livewire) => $livewire->processing ? 'heroicon-o-arrow-path' : 'heroicon-m-plus')
                    ->disabled(fn(self $livewire) => $livewire->processing ?? false)
            )
            ->paginated(false)

            // add "create new record" button in table header
            ->headerActions([
                CreateAction::make()
                    ->label('ADD QUESTION')
                    ->icon(fn(self $livewire) => $livewire->processing ? 'heroicon-o-arrow-path' : 'heroicon-m-plus')
                    ->button()
                    ->color('danger')
                    ->extraAttributes(['class' => 'add_questions_btn'])
                    ->modalWidth(MaxWidth::SevenExtraLarge)
                    ->modalHeading('ADD QUESTION')
                    ->extraModalWindowAttributes(['class' => 'add_questions_modal'])
                    ->modalSubmitAction(fn(StaticAction $action) => $action
                        ->extraAttributes(['class' => 'buttona shadow-none !ring-0 border-0']))
                    ->modalCancelAction(fn(StaticAction $action) => $action
                        ->extraAttributes(['class' => 'buttonb shadow-none !ring-0 ']))
                    ->createAnother(false)
                    ->form($questionForm)
                    ->disabled(fn(self $livewire) => $livewire->processing ?? false),
            ])

            // show form content in modal popup
            ->actions([

                Action::make('view_edit_question')
                    ->label('EDIT QUESTION')
                    ->icon('heroicon-m-pencil')
                    ->extraAttributes(['class' => 'py-2 shadow-none'])
                    ->extraModalWindowAttributes(['class' => 'add_questions_modal'])
                    ->modalSubmitAction(fn(StaticAction $action) => $action
                        ->extraAttributes(['class' => 'buttona shadow-none !ring-0 border-0']))
                    ->modalCancelAction(fn(StaticAction $action) => $action
                        ->extraAttributes(['class' => 'buttonb shadow-none !ring-0 ']))
                    ->button()
                    ->color('blue')
                    // disable editing uploaded custom questions
                    // Note: we use survey_rows.path to determine if a custom question is uploaded or not now,
                    // when we start using survey_rows.path for matching submissions to SurveyRow entries,
                    // we will need to create a new column as a flag for indication
                    ->disabled(fn(self $livewire, SurveyRow $record) => $record->path != null || $livewire->processing)
                    ->tooltip(fn(SurveyRow $record) => $record->path == null ? 'You cannot directly edit questions created via an Excel Import. Please download the template above and edit the question inside Excel' : '')
                    // set more horizontal space for modal popup
                    ->modalWidth(MaxWidth::SevenExtraLarge)
                    // fill the form with existing data
                    ->fillForm(fn(SurveyRow $record): array => [
                        'xlsform_module_version_id' => $xlsformModuleVersion->id,
                        'id' => $record->id,
                        'type' => $record->type,
                        'name' => $record->name,
                    ])
                    ->form($questionForm)
                    ->icon(fn(self $livewire) => $livewire->processing ? 'heroicon-o-arrow-path' : 'heroicon-o-pencil'),



                // add "DELETE QUESTION" button in table row instead of inside modal popup
                DeleteAction::make()
                    ->label('DELETE QUESTION')
                    ->extraAttributes(['class' => 'py-2 shadow-none'])
                    ->button()
                    ->modalHeading('Delete Question')
                    ->action(function (DeleteAction $action) {

                        $choiceList = $action->process(static fn(Model $record) => $record->choiceList);

                        $result = $action->process(static fn(Model $record) => $record->delete());

                        if (!$result) {
                            $this->failure();

                            return;
                        }

                        // Lists for these custom questions are always only for the individual question.
                        if ($choiceList instanceof ChoiceList) {
                            $choiceList->delete();
                        }

                        $action->success();
                    })
                    ->icon(fn(self $livewire) => $livewire->processing ? 'heroicon-o-arrow-path' : 'heroicon-o-trash')
                    ->disabled(fn(self $livewire) => $livewire->processing ?? false),

            ]);
    }
}
