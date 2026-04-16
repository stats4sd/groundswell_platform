<?php

namespace App\Filament\App\Clusters\Localisations\Resources;

use App\Filament\App\Clusters\Localisations;
use App\Filament\App\Clusters\Localisations\Resources\ChoiceListEntryResource\Pages\ListChoiceListEntries;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Unique;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;

class ChoiceListEntryResource extends Resource
{
    protected static ?string $model = ChoiceListEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?int $navigationSort = 2;

    protected static ?string $cluster = Localisations::class;

    protected static bool $isScopedToTenant = false; // use custom query instead to get both global entries and team-entries.

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // only global and team-owned items
            ->where(fn (Builder $query) => $query
                ->whereHas('owner', fn (Builder $query) => $query
                    ->where('teams.id', HelperService::getCurrentOwner()?->id
                    ))
                ->orWhere('owner_id', null)
            )
            // only localisable lists
            ->whereHas('choiceList', fn (Builder $query) => $query
                ->where('is_localisable', true)
            )
            // and only entries that are not linked to lists that are datasets
            ->whereDoesntHave('choiceList', fn (Builder $query) => $query
                ->where('is_dataset', true)
            );

    }

    public static function getNavigationItems(): array
    {
        /** @var Collection<ChoiceList> $lists */
        $lists = ChoiceList::where('is_localisable', true)
            ->where('has_custom_handling', false)
            ->get();

        if (! $lists) {
            return [

            ];
        }

        return $lists
            ->map(fn (ChoiceList $choiceList) => NavigationItem::make($choiceList->list_name)
                ->group('Choice Lists')
//                ->icon(fn() => HelperService::getCurrentOwner()?->hasCompletedLookupList($choiceList) ? 'heroicon-o-check' : 'heroicon-o-exclamation-circle')
//                ->activeIcon(fn() => HelperService::getCurrentOwner()?->hasCompletedLookupList($choiceList) ? 'heroicon-o-check' : 'heroicon-o-exclamation-circle')
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName().'.*')
                    && request()->get('choiceListName') === $choiceList->list_name
                )
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->sort(static::getNavigationSort())
                ->url(static::getNavigationUrl().'?'.http_build_query(['choiceListName' => $choiceList->list_name]))
            )->toArray();
    }

    /** @noinspection PhpRedundantOptionalArgumentInspection */
    public static function getFormSchema(ChoiceList $choiceList): array
    {
        if (isset($choiceList->properties['extra_properties'])) {

            $propFields = collect($choiceList->properties['extra_properties'])
                ->map(fn ($property) => TextInput::make('properties.'.$property['name'])
                    ->label($property['label'])
                    ->helperText($property['hint'])
                );
        } else {
            $propFields = collect([]);
        }

        return [
            Hidden::make('owner_id')
                ->default(fn () => HelperService::getCurrentOwner()?->id),
            Hidden::make('choice_list_id')
                ->formatStateUsing(fn (?ChoiceListEntry $record, ListChoiceListEntries $livewire) => $record ? $record->choiceList->id : ChoiceList::where('list_name', $livewire->choiceListName)->first()->id),
            TextInput::make('name')->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule, Get $get) {
                    return $rule
                        ->where('choice_list_id', $get('choice_list_id'))
                        ->where('owner_id', $get('owner_id'));
                }),
            Repeater::make('languageStrings')
                ->label('Add Labels for the following languages:')
                ->relationship('languageStrings')
                ->minItems(fn () => HelperService::getCurrentOwner()?->locales->count())
                ->maxItems(fn () => HelperService::getCurrentOwner()?->locales->count())
                ->formatStateUsing(function (?ChoiceListEntry $record, $state, ListChoiceListEntries $livewire) {
                    if ($record) {
                        return $state;
                    }

                    $locales = HelperService::getCurrentOwner()?->locales;

                    return $locales->map(fn (Locale $locale) => [
                        'language_string_type_id' => LanguageStringType::where('name', 'label')->firstOrFail()->id,
                        'locale_id' => $locale->id,
                        'text' => '',
                    ])->toArray();
                })
                ->schema([
                    Hidden::make('locale_id'),
                    Hidden::make('language_string_type_id'),
                    TextInput::make('text')
                        ->label(function (Get $get) {
                            $locale = Locale::find($get('locale_id'));

                            return 'Label::'.$locale?->language_label;
                        })
                        ->required(),
                ])
                ->addable(false)
                ->deletable(false),
            ...$propFields->toArray(),
        ];

    }

    public static function table(Table $table): Table
    {
        // languages??
        $locales = HelperService::getCurrentOwner()?->locales;

        $labelColumns = $locales->map(function (Locale $locale) {
            return TextColumn::make('label_'.$locale->language->id)
                ->label('label::'.$locale->language_label)
                ->state(function (ChoiceListEntry $record) use ($locale) {

                    return $record->languageStrings
                        // only labels
                        ->filter(fn (LanguageString $languageString) => $languageString->language_string_type_id === LanguageStringType::where('name', 'label')->firstOrFail()->id)
                        // only ones for the current locale
                        ->filter(fn (LanguageString $languageString) => $languageString->locale_id === $locale->id)
                        ->first()
                        ?->text ?? '';
                });
        });

        return $table
            ->columns([
                TextColumn::make('choiceList.list_name')->label('list_name'),
                TextColumn::make('name')->label('name'),
                ...$labelColumns->toArray(),
                IconColumn::make('is_customised_entry')
                    ->label('Localised Entry')
                    ->boolean(),
            ])
            ->recordClasses(fn (ChoiceListEntry $record) => $record->isRemoved(HelperService::getCurrentOwner()) ? 'opacity-50' : '');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ChoiceListEntryResource\Pages\ListChoiceListEntries::route('/'),
        ];
    }
}
