<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\GlobalIndicatorResource\Pages;
use App\Filament\Admin\Resources\GlobalIndicatorResource\RelationManagers;
use App\Models\Holpa\GlobalIndicator;
use App\Models\Holpa\Theme;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GlobalIndicatorResource extends Resource
{
    protected static ?string $model = GlobalIndicator::class;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';
    protected static ?string $navigationGroup = 'HOLPA Indicators';

    // protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('name')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Select::make('theme_id')
                    ->relationship('theme', 'name')
                    ->options(Theme::all()->pluck('displayName', 'id'))
                    ->required()
                    ->createOptionForm([
                        Forms\Components\Select::make('domain_id')
                            ->relationship('domain', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('module')
                            ->options([
                                'Context' => 'Context',
                                'Agroecology' => 'Agroecology',
                                'Performance' => 'Performance',
                            ]),
                        Forms\Components\TextInput::make('name'),
                    ])
                    ->preload()
                    ->searchable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('theme.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('theme.domain.name')
                    ->label('Domain')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('theme.module')
                    ->label('Module')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('localindicators_count')
                    ->label('# Local indicators')
                    ->counts('localindicators')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            RelationManagers\LocalIndicatorsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGlobalIndicators::route('/'),
            'create' => Pages\CreateGlobalIndicator::route('/create'),
            'edit' => Pages\EditGlobalIndicator::route('/{record}/edit'),
        ];
    }
}
