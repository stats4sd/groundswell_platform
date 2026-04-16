<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources;

use App\Filament\App\Clusters\LocationLevels;
use App\Filament\App\Clusters\LocationLevels\Resources\FarmResource\Pages;
use App\Models\SampleFrame\Farm;
use App\Models\SampleFrame\LocationLevel;
use Faker\Extension\Helper;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Stats4sd\FilamentOdkLink\Services\HelperService;
use Illuminate\Validation\Rules\Unique;

class FarmResource extends Resource
{
    protected static ?string $model = Farm::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $cluster = LocationLevels::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    public static function form(Form $form): Form
    {
        // find location level that has farms
        // 1. use location level name as location_id's label
        // 2. use location level's locations for user selection
        $locationLevelWithFarms = auth()->user()->latestTeam->locationLevels->where('has_farms', 1)->first();

        return $form
            ->schema([

                Forms\Components\Hidden::make('owner_id')
            ->default(HelperService::getCurrentOwner()->id),
                Forms\Components\Select::make('location_id')
                    ->label('Select the ' . $locationLevelWithFarms->name . ' for this farm')
                    ->options($locationLevelWithFarms->locations->pluck('name', 'id')),

                Forms\Components\TextInput::make('team_code')
                    ->label('Unique code')
                    ->helperText('Please enter a unique code to identify this farm for your team')
                    // team code should be unique per team, as other teams may have the same team code
                    ->unique(modifyRuleUsing: function (Unique $rule) {
                        return $rule->where('owner_id', HelperService::getCurrentOwner()->id);
                    })
                    ->maxLength(255),

                Forms\Components\Section::make('Personally Identifiable information')
                    ->description('This section lets you add any information about the farm or farmer that lets your enumerators personally identify the farm / farmer.')
                    ->schema([
                        Forms\Components\KeyValue::make('identifiers')
                            ->hint('For example: farm name, name of household head, phone number, physical address.')
                            ->helperText('Information added here will be available to your team through data downloads, and if required can be included in the ODK survey to help enumerators ensure they reach the correct farms. However, it will never be included in any final data products that are intended for sharing beyond your team, and no-one outside of your team will have access to it.'),
                    ]),

                Forms\Components\Section::make('Other Farm Information')
                    ->description('This section lets you add information about the farm that is not personally identifiable.')
                    ->schema([
                        Forms\Components\KeyValue::make('properties')
                            ->hint('For example: gender of household head, active member of (name of your intervention project) - yes / no, farm typology information')
                            ->helperText('The purpose of information here is to allow you to disaggregate results by these variables. For example, if you are interested in comparing results from farms that took part in a specific training activity with farms that did not take part, you should include that as a variable here. Variables entered here will be available in exported datasets so they can be used in your analysis.'),
                    ]),

                Forms\Components\Section::make('GPS')
                    ->description('Optionally, add the GPS co-ordinates for the farm')
                    ->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->numeric()
                            ->minValue(-90)
                            ->maxValue(90),
                        Forms\Components\TextInput::make('longitude')
                            ->numeric()
                            ->minValue(-180)
                            ->maxValue(180),
                        Forms\Components\TextInput::make('altitude')
                            ->numeric()
                            ->minValue(-1240)
                            ->maxValue(60000),
                        Forms\Components\TextInput::make('accuracy')
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
                fn(LocationLevel $locationLevel) => Tables\Columns\TextColumn::make("location_{$locationLevel->id}")
                    ->getStateUsing(fn($record) => $record->location->location_level_id === $locationLevel->id ? $record->location->name : '')
                    ->label($locationLevel->name)
                    ->sortable()
                    ->searchable()
            );

        $identifiers = $farms->map(fn(Farm $farm) => $farm->identifiers?->keys())
            ->flatten()->unique()->values();

        $idColumns = $identifiers->map(fn($identifier) => Tables\Columns\TextColumn::make("identifiers.{$identifier}")->label(ucfirst($identifier))->sortable()->searchable());

        $properties = $farms->map(fn(Farm $farm) => $farm->properties?->keys())
            ->flatten()->unique()->values();

        $propertyColumns = $properties->map(fn($property) => Tables\Columns\TextColumn::make("properties.{$property}")->label(ucfirst($property))->sortable()->searchable());

        return $table
            ->columns([
                ...$locationLevelColumns,
                Tables\Columns\TextColumn::make('team_code')->label('Unique Code')
                    ->sortable()
                    ->searchable(),
                ...$idColumns,
                ...$propertyColumns,
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    // disable New Farm button if there is no location level with farm
                    ->disabled(fn() => HelperService::getCurrentOwner()->locationLevels()->where('has_farms', 1)->count() < 1),

                    // TODO: We have two location levels: district and sub-district. Can user select which location level when creating a new farm manually?
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListFarms::route('/'),
            'import' => Pages\ImportLocationsAndFarms::route('/import'),
        ];
    }
}
