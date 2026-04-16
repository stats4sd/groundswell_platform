<?php

namespace App\Filament\Admin\Resources\GlobalIndicatorResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LocalIndicatorsRelationManager extends RelationManager
{
    protected static string $relationship = 'localIndicators';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('theme.name')
                    ->label('Theme')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('theme.domain')
                    ->label('Domain')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('theme.module')
                    ->label('Module')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('team.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('globalindicator_count')
                    ->label('# Global indicators')
                    ->counts('globalindicator')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // this link is created when local indicators are mapped to global indicators. We may not use it, but may as well create it to try it out
                // Tables\Actions\AttachAction::make()
                //     ->attachAnother(false),
            ])
            ->actions([
                // Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
