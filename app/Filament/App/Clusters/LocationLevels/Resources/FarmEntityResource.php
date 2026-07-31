<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources;

use App\Filament\App\Clusters\LocationLevels;
use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Pages\CreateFarmEntity;
use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Pages\EditFarmEntity;
use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Pages\ImportLocationsAndFarmEntities;
use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Pages\ListFarmEntities;
use App\Models\SampleFrame\FarmEntity;
use App\Services\HelperService;
use App\Services\OdkFarmEntityService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

// ODK-Central-Entities-backed Farm CRUD page. See docs/plans/odk-entities-farm-crud.md.
// Nav-hidden - reached via the Survey Locations index and the location-levels cluster nav.
class FarmEntityResource extends Resource
{
    protected static ?string $model = FarmEntity::class;

    protected static ?string $slug = 'farm-entities';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $cluster = LocationLevels::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    public static function getModelLabel(): string
    {
        return t('Farm');
    }

    public static function getPluralModelLabel(): string
    {
        return t('Farms');
    }

    public static function form(Schema $schema): Schema
    {
        $locationLevelWithFarms = HelperService::getCurrentOwner()->locationLevels->where('has_farms', 1)->first();

        return $schema->components([

            Hidden::make('owner_id')
                ->default(fn () => HelperService::getCurrentOwner()->id),

            Select::make('location_id')
                ->label($locationLevelWithFarms ? t('Select the').' '.$locationLevelWithFarms->name.' '.t('for this farm') : t('Location'))
                ->options($locationLevelWithFarms?->locations->pluck('name', 'id') ?? [])
                ->required(),

            TextInput::make('team_code')
                ->label(t('Unique code'))
                ->helperText(t('Please enter a unique code to identify this farm for your team'))
                // team code should be unique per team, as other teams may have the same team code
                ->unique(modifyRuleUsing: function (Unique $rule) {
                    return $rule->where('owner_id', HelperService::getCurrentOwner()->id);
                })
                ->required()
                ->maxLength(255),

            Section::make(t('Personally Identifiable information'))
                ->description(t('This section lets you add any information about the farm or farmer that lets your enumerators personally identify the farm / farmer.'))
                ->schema([
                    KeyValue::make('identifiers')
                        ->hint(t('For example: farm name, name of household head, phone number, physical address.'))
                        ->helperText(t('This data is stored as a property on the farm\'s ODK Central Entity record.')),
                ]),

            Section::make(t('Other Farm Information'))
                ->description(t('This section lets you add information about the farm that is not personally identifiable.'))
                ->schema([
                    KeyValue::make('properties')
                        ->hint(t('For example: gender of household head, active member of (name of your intervention project) - yes / no, farm typology information')),
                ]),

            Section::make(t('GPS'))
                ->description(t('Optionally, add the GPS co-ordinates for the farm'))
                ->schema([
                    TextInput::make('latitude')->label(t('Latitude'))->numeric()->minValue(-90)->maxValue(90),
                    TextInput::make('longitude')->label(t('Longitude'))->numeric()->minValue(-180)->maxValue(180),
                    TextInput::make('altitude')->label(t('Altitude'))->numeric()->minValue(-1240)->maxValue(60000),
                    TextInput::make('accuracy')->label(t('Accuracy'))->numeric(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $team = HelperService::getCurrentOwner();
        $service = app(OdkFarmEntityService::class);
        $dataset = $service->ensureDataset($team);

        // Dynamic identifier/property columns are driven by DatasetVariable (schema-level,
        // stays local). Values themselves are never persisted locally - $livewire->liveFarmData is the
        // live feed ListFarmEntities::mount() fetched for this page load (see
        // OdkFarmEntityService::refreshFromCentral()). The location cascade attributes
        // (loc{n}/loc{n}_name/loc{n}_type) and GPS are excluded here via the 'loc'
        // classification - GPS has its own dedicated form fields and the cascade attributes
        // are owned by the dedicated Location column.
        $propertyColumns = $dataset->variables()
            ->where('name', '!=', 'team_code')
            ->get()
            ->reject(fn ($variable) => $service->propertyDescription($variable->name) === 'loc')
            ->map(fn ($variable) => TextColumn::make("property_{$variable->name}")
                ->label($variable->label)
                ->getStateUsing(fn (FarmEntity $record, $livewire) => $livewire->liveFarmData[$record->odk_uuid]['data'][$variable->name] ?? null));

        return $table
            ->columns([
                TextColumn::make('location.name')->label(t('Location'))->placeholder(t('Unknown (created outside app)'))->sortable()->searchable(),
                TextColumn::make('team_code')->label(fn () => t('Unique code'))->sortable()->searchable(),
                ...$propertyColumns,
            ])
            ->filters([
                // Central soft-deletes an entity rather than removing it, mirrored locally
                // via FarmEntity's SoftDeletes - this exposes the with/without/only-trashed
                // toggle Filament already knows how to build for a soft-deleting model.
                TrashedFilter::make(),
            ])
            ->recordActions([
                // Unlike Delete/Restore, EditAction has no built-in trashed-awareness -
                // hidden explicitly so a soft-deleted farm can't be edited.
                EditAction::make()
                    ->hidden(fn (FarmEntity $record) => $record->trashed()),
                // Overrides the default delete/restore behaviour - a farm's ODK Central
                // entity must be soft-deleted/restored too, not just the local row.
                // DeleteAction/RestoreAction already auto-hide/auto-show based on
                // $record->trashed(), so no extra visibility wiring is needed here.
                DeleteAction::make()
                    ->action(fn (FarmEntity $record) => app(OdkFarmEntityService::class)->deleteFarm($record)),
                RestoreAction::make()
                    ->action(fn (FarmEntity $record) => app(OdkFarmEntityService::class)->restoreFarm($record)),
            ])
            ->headerActions([
                CreateAction::make()
                    // disable New Farm button if there is no location level with farms
                    ->disabled(fn () => $team->locationLevels()->where('has_farms', 1)->count() < 1),
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
            'index' => ListFarmEntities::route('/'),
            'create' => CreateFarmEntity::route('/create'),
            'edit' => EditFarmEntity::route('/{record}/edit'),
            'import' => ImportLocationsAndFarmEntities::route('/import'),
        ];
    }
}
