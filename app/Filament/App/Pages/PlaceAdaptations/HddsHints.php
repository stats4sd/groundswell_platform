<?php

namespace App\Filament\App\Pages\PlaceAdaptations;

use Filament\Support\Enums\Width;
use Filament\Support\Enums\TextSize;
use Filament\Actions\EditAction;
use Filament\Schemas\Components\Fieldset;
use App\Filament\App\Pages\SurveyDashboard;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class HddsHints extends Page implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.app.pages.place-adaptations.hdds-hints';

    protected static ?string $title = 'Localisation: HDDS hints';

    public Team $team;

    public XlsformModuleVersion $xlsformModuleVersion;

    protected Width|string|null $maxContentWidth = 'max-w-10xl';


    public static function canAccess(): bool
    {
        return auth()->user()->can('view place-based adaptations');
    }

    public function mount(): void
    {
        $team = HelperService::getCurrentOwner();

        if ($team === null) {
            abort(404);
        }

        $this->team = $team;

        $moduleVersion = $team->hddsModuleVersion();

        if ($moduleVersion === null) {
            abort(404);
        }

        // First visit: the team is still pointing at the global HDDS version.
        // Clone it so their edits are isolated, then swap the pivot entries on
        // all of this team's xlsforms to reference the new team-owned version.
        if ($moduleVersion->owner_id !== $team->id) {
            $globalVersion = $moduleVersion;
            $moduleVersion = $globalVersion->cloneForOwner($team);

            $xlsformIds = $team->xlsforms()->pluck('xlsforms.id');
            foreach ($xlsformIds as $xlsformId) {
                $linked = $globalVersion->xlsforms()->wherePivot('xlsform_id', $xlsformId)->first();
                if ($linked) {
                    $order = $linked->pivot->order;
                    $globalVersion->xlsforms()->detach($xlsformId);
                    $moduleVersion->xlsforms()->attach($xlsformId, ['order' => $order]);
                }
            }
        }

        $this->xlsformModuleVersion = $moduleVersion->load('surveyRows.languageStrings');
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
            PlaceAdaptationsIndex::getUrl() => t('Place-based adaptations'),
            static::getUrl() => static::getTitle(),
        ];
    }

    public function table(Table $table): Table
    {
        /** @var array<int, Locale> $locales */
        $locales = $this->team->locales->all();

        $localeColumns = [];

        foreach ($locales as $locale) {
            $localeColumns[] = TextColumn::make("label_{$locale->id}")
                ->label("Label — {$locale->language_label}")
                ->wrap()
                ->weight(FontWeight::Bold)
                ->state(fn (SurveyRow $record): ?string => $record->getLanguageString('label', $locale));

            $localeColumns[] = TextColumn::make("hint_{$locale->id}")
                ->label("Hint — {$locale->language_label}")
                ->wrap()
                ->state(fn (SurveyRow $record): ?string => $record->getLanguageString('hint', $locale));
        }

        return $table
            ->query(
                fn () => SurveyRow::query()
                    ->where('xlsform_module_version_id', $this->xlsformModuleVersion->id)
                    ->orderBy('row_number'),
            )
            ->columns([
                TextColumn::make('type')->label('Type')->size(TextSize::ExtraSmall),
                TextColumn::make('name')->label('Variable name')->wrap()->size(TextSize::ExtraSmall),
                ...$localeColumns,
            ])
            ->recordClasses(fn (SurveyRow $record): string => match ($record->type) {
                'begin group', 'begin_group' => 'row-group-begin',
                'end group', 'end_group' => 'row-group-end',
                default => '',
            })
            ->paginated(false)
            ->recordActions([
                EditAction::make('edit_hints')
                    ->hidden(function (SurveyRow $record) use ($locales): bool {
                        foreach ($locales as $locale) {
                            $hint = $record->getLanguageString('hint', $locale);
                            if ($hint !== null && $hint !== '') {
                                return false;
                            }
                        }

                        return true;
                    })
                    ->label('EDIT HINTS')
                    ->modalHeading('Edit hints')
                    ->fillForm(function (SurveyRow $record) use ($locales): array {
                        $hints = [];

                        foreach ($locales as $locale) {
                            $hints[$locale->id] = $record->getLanguageString('hint', $locale);
                        }

                        return ['hints' => $hints];
                    })
                    ->schema(array_map(
                        fn (Locale $locale) => Fieldset::make($locale->language_label)
                            ->columns(1)
                            ->schema([
                                Placeholder::make("label_{$locale->id}")
                                    ->label('Label')
                                    ->content(fn (SurveyRow $record): ?string => $record->getLanguageString('label', $locale)),
                                TextInput::make("hints.{$locale->id}")
                                    ->label('Hint'),
                            ]),
                        $locales,
                    ))
                    ->action(function (array $data, SurveyRow $record): void {
                        $hintTypeId = LanguageStringType::where('name', 'hint')->value('id');

                        foreach ($data['hints'] as $localeId => $text) {
                            $record->languageStrings()->updateOrCreate(
                                [
                                    'locale_id' => $localeId,
                                    'language_string_type_id' => $hintTypeId,
                                ],
                                ['text' => $text],
                            );
                        }

                        // Touch the SurveyRow so its saved() hook flags the team's
                        // xlsforms as needing a draft update (draft_needs_update = true).
                        $record->touch();
                    }),
            ]);
    }
}
