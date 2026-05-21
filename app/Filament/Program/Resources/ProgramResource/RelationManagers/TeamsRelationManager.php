<?php

namespace App\Filament\Program\Resources\ProgramResource\RelationManagers;

use Filament\Tables;
use Filament\Tables\Table;
use Stats4sd\FilamentTeamManagement\Filament\Program\Resources\ProgramResource\RelationManagers\TeamsRelationManager as BaseTeamsRelationManager;

class TeamsRelationManager extends BaseTeamsRelationManager
{
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\AttachAction::make()
                    ->label('Add Existing Team to program')
                    ->visible(fn () => auth()->user()->can('maintain my program')),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->label('Remove Team')
                    ->modalSubmitActionLabel('Remove Team')
                    ->modalHeading('Remove Team from Program')
                    ->visible(fn () => auth()->user()->can('maintain my program')),
            ]);
    }
}
