<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\KeyValue;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\App\Clusters\LocationLevels\Resources\FarmResource\Pages\ListFarms;
use App\Filament\App\Clusters\LocationLevels\Resources\FarmResource\Pages\ImportLocationsAndFarms;
use App\Filament\App\Clusters\LocationLevels;
use App\Filament\App\Clusters\LocationLevels\Resources\FarmResource\Pages;
use App\Models\SampleFrame\Farm;
use App\Models\SampleFrame\LocationLevel;
use Faker\Extension\Helper;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Stats4sd\FilamentOdkLink\Services\HelperService;
use Illuminate\Validation\Rules\Unique;

class FarmResource extends Resource
{
    protected static ?string $model = Farm::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return t('Farm');
    }

    public static function getPluralModelLabel(): string
    {
        return t('Farms');
    }

    protected static ?string $cluster = LocationLevels::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    public static function form(Schema $schema): Schema
    {
        // find location level that has farms
        // 1. use location level name as location_id's label
        // 2. use location level's locations for user selection
        $locationLevelWithFarms = auth()->user()->latestTeam->locationLevels->where('has_farms', 1)->first();

        return $schema
            ->components([

                Hidden::make('owner_id')
            ->default(HelperService::getCurrentOwner()->id),
                Select::make('location_id')
                    ->label(t('Select the') . ' ' . $locationLevelWithFarms->name . ' ' . t('for this farm'))
                    ->options($locationLevelWithFarms->locations->pluck('name', 'id')),

                TextInput::make('team_code')
                    ->label(t('Unique code'))
                    ->helperText(t('Please enter a unique code to identify this farm for your team'))
                    // team code should be unique per team, as other teams may have the same team code
                    ->unique(modifyRuleUsing: function (Unique $rule) {
                        return $rule->where('owner_id', HelperService::getCurrentOwner()->id);
                    })
                    ->maxLength(255),

                Section::make(t('Personally Identifiable information'))
                    ->description(t('This section lets you add any information about the farm or farmer that lets your enumerators personally identify the farm / farmer.'))
                    ->schema([
                        KeyValue::make('identifiers')
                            ->hint(t('For example: farm name, name of household head, phone number, physical address.'))
                            ->helperText(t('Information added here will be available to your team through data downloads, and if required can be included in the ODK survey to help enumerators ensure they reach the correct farms. However, it will never be included in any final data products that are intended for sharing beyond your team, and no-one outside of your team will have access to it.')),
                    ]),

                Section::make(t('Other Farm Information'))
                    ->description(t('This section lets you add information about the farm that is not personally identifiable.'))
                    ->schema([
                        KeyValue::make('properties')
                            ->hint(t('For example: gender of household head, active member of (name of your intervention project) - yes / no, farm typology information'))
                            ->helperText(t('The purpose of information here is to allow you to disaggregate results by these variables. For example, if you are interested in comparing results from farms that took part in a specific training activity with farms that did not take part, you should include that as a variable here. Variables entered here will be available in exported datasets so they can be used in your analysis.')),
                    ]),

                Section::make(t('GPS'))
                    ->description(t('Optionally, add the GPS co-ordinates for the farm'))
                    ->schema([
                        TextInput::make('latitude')
                            ->label(t('Latitude'))
                            ->numeric()
                            ->minValue(-90)
                            ->maxValue(90),
                        TextInput::make('longitude')
                            ->label(t('Longitude'))
                            ->numeric()
                            ->minValue(-180)
                            ->maxValue(180),
                        TextInput::make('altitude')
                            ->label(t('Altitude'))
                            ->numeric()
                            ->minValue(-1240)
                            ->maxValue(60000),
                        TextInput::make('accuracy')
                            ->label(t('Accuracy'))
                            ->numeric(),
                    ])->columns(2),

            ]);
    }

    public static function table(Table $table): Table
    {
        $farms = Farm::all()->where('owner_id', HelperService::getCurrentOwner()->id);

        $locationLevelColumns = $farms->map(fn(Farm $farm) => $farm->location->locationLevel)
            ->unique()
            ->values()
            ->map(
                fn(LocationLevel $locationLevel) => TextColumn::make("location_{$locationLevel->id}")
                    ->getStateUsing(fn($record) => $record->location->location_level_id === $locationLevel->id ? $record->location->name : '')
                    ->label($locationLevel->name)
                    ->sortable()
                    ->searchable()
            );

        $identifiers = $farms->map(fn(Farm $farm) => $farm->identifiers?->keys())
            ->flatten()->unique()->values();

        $idColumns = $identifiers->map(fn($identifier) => TextColumn::make("identifiers.{$identifier}")->label(ucfirst($identifier))->sortable()->searchable());

        $properties = $farms->map(fn(Farm $farm) => $farm->properties?->keys())
            ->flatten()->unique()->values();

        $propertyColumns = $properties->map(fn($property) => TextColumn::make("properties.{$property}")->label(ucfirst($property))->sortable()->searchable());

        return $table
            ->columns([
                ...$locationLevelColumns,
                TextColumn::make('team_code')->label(fn () => t('Unique code'))
                    ->sortable()
                    ->searchable(),
                ...$idColumns,
                ...$propertyColumns,
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    // disable New Farm button if there is no location level with farm
                    ->disabled(fn() => HelperService::getCurrentOwner()->locationLevels()->where('has_farms', 1)->count() < 1),

                    // TODO: We have two location levels: district and sub-district. Can user select which location level when creating a new farm manually?
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => ListFarms::route('/'),
            'import' => ImportLocationsAndFarms::route('/import'),
        ];
    }
}
