<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Schemas\Components\Section;
use App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource\Pages\ListLocationLevels;
use App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource\Pages\ViewLocationLevel;
use App\Filament\App\Clusters\LocationLevels;
use App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource\Pages;
use App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource\RelationManagers\LocationsRelationManager;
use App\Models\SampleFrame\LocationLevel;
use App\Services\HelperService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

class LocationLevelResource extends Resource
{
    protected static ?string $model = LocationLevel::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    protected static ?string $cluster = LocationLevels::class;

    public static function getNavigationItems(): array
    {
        // make sure the original nav item is only 'active' when the index page is active.
        $original = collect(parent::getNavigationItems())
            ->map(function ($item) {
                return $item->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName().'.index'));
            })->toArray();

        $baseRoute = static::getUrl('index');

        $navItems = LocationLevel::query()
            ->orderBy('parent_id')
            ->get()
            ->map(function ($level) use ($baseRoute) {
                return NavigationItem::make(Str::plural($level->name))
                    ->url($baseRoute.'/'.$level->slug)
                    ->isActiveWhen(function () use ($level) {
                        $isViewRoute = request()->routeIs(static::getRouteBaseName().'.view');
                        $isMatchingRecord = request()->route('record') === $level->slug;

                        return $isViewRoute && $isMatchingRecord;
                    });
            });

        $farmNavItem = NavigationItem::make('Farms')
            ->url(FarmResource::getUrl())
            ->isActiveWhen(fn () => request()->routeIs(FarmResource::getRouteBaseName().'.index'));

        return array_merge($original, $navItems->toArray(), [$farmNavItem]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->label(fn () => t('Is this location level a sub-level of another level?'))
                    ->helperText(fn () => t('E.g. "Village" may be a sub-level of "District", and "District" may be a sub-level of "Province".'))
                    // exclude the current location level record, to prevent self-referencing loops
                    ->relationship('parent', 'name', ignoreRecord: true)
                    ->hidden(fn (?LocationLevel $record) => $record && $record->top_level === 1),
                TextInput::make('name')
                    ->label(fn () => t('Name'))
                    ->required()
                    ->maxLength(255),
                Toggle::make('has_farms')
                    ->label(fn () => t('Are there farms at this level?'))
                    ->helperText(fn () => t('Only say yes if there are farms directly at this location level, not in a lower location level. E.g. "Village" may have farms, but "District" may not.')),
                Hidden::make('owner_id')
                    ->default(fn () => HelperService::getCurrentOwner()->id),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(fn () => t('Name'))
                    ->sortable(),
                TextColumn::make('parent.name')
                    ->label(fn () => t('Parent'))
                    ->sortable()
                    ->placeholder(fn () => t('Top Level')),
                TextColumn::make('locations_count')
                    ->counts('locations')
                    ->label(fn () => t('No. of Entries'))
                    ->sortable(),
                IconColumn::make('has_farms')
                    ->label(fn () => t('Has farms'))
                    ->boolean()
                    ->sortable(),
            ])
            ->paginated(false)
            ->defaultSort('parent_id', 'asc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(fn () => t('Key Details'))
                ->schema([
                    TextEntry::make('name')->label(fn () => t('Level')),
                    TextEntry::make('parent.name')->label(fn () => t('Parent Level'))->hidden(fn (LocationLevel $record) => $record->top_level === 1),
                ]),
        ])
            ->columns(2);
    }

    public static function getRelations(): array
    {
        return [
            LocationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocationLevels::route('/'),
            'view' => ViewLocationLevel::route('/{record}'),
        ];
    }
}
