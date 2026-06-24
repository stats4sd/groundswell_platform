<?php

namespace App\Filament\App\Resources\TeamResource\RelationManagers;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
                TextColumn::make('email')
                    ->label(t('Email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('team.name')
                    ->label(t('Team'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('inviter.name')
                    ->label(t('Invited By'))
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_confirmed')
                    ->label(t('Confirmed'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(t('Invited At'))
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(t('Updated At'))
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
