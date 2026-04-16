<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ThemeResource\Pages;
use App\Filament\Admin\Resources\ThemeResource\RelationManagers;
use App\Models\Holpa\Domain;
use App\Models\Holpa\Theme;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ThemeResource extends Resource
{
    protected static ?string $model = Theme::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'HOLPA Indicators';
    // protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('module')
                    ->maxLength(255),
                Forms\Components\Select::make('domain_id')
                    ->label('Domain')
                    ->options(Domain::all()->pluck('name', 'id')),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('module')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('domain.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('globalindicators_count')
                    ->label('# Global indicators')
                    ->counts('globalindicators')
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
            RelationManagers\GlobalIndicatorsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListThemes::route('/'),
            'create' => Pages\CreateTheme::route('/create'),
            'edit' => Pages\EditTheme::route('/{record}/edit'),
        ];
    }
}
