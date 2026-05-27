<?php

namespace App\Filament\App\Resources\TeamResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InvitesRelationManager extends RelationManager
{
    protected static string $relationship = 'invites';

    // we do not need a form here, team invitation form is defined in User resources as a headerAction
    //
    // public function form(Form $form): Form
    // {
    //     return $form
    //         ->schema([
    //             Forms\Components\TextInput::make('email')
    //                 ->required()
    //                 ->maxLength(255),
    //         ]);
    // }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label(t('Email'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('team.name')
                    ->label(t('Team'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('inviter.name')
                    ->label(t('Invited By'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_confirmed')
                    ->label(t('Confirmed'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(t('Invited At'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(t('Updated At'))
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
