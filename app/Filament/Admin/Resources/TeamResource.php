<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TeamResource\Pages;
use App\Filament\Admin\Resources\TeamResource\RelationManagers\XlsformsRelationManager;
use App\Models\Team;
use Filament\Actions\ForceDeleteAction;
use Filament\Tables;
use Filament\Tables\Table;
use Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\TeamResource\RelationManagers\InvitesRelationManager;
use Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\TeamResource\RelationManagers\UsersRelationManager;

class TeamResource extends \Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\TeamResource
{
    protected static ?string $model = Team::class;

    protected static bool $shouldRegisterNavigation = true;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('programs.name')
                    ->searchable()
                    ->badge()
                    ->color('success')
                    ->visible(config('filament-team-management.use_programs')),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('# Users')
                    ->counts('users')
                    ->sortable(),
                Tables\Columns\TextColumn::make('invites_count')
                    ->label('# Invites')
                    ->counts('invites')
                    ->sortable(),
                Tables\Columns\TextColumn::make('xlsforms_count')
                    ->label('# Xlsforms')
                    ->counts('xlsforms')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ForceDeleteAction::make()
                ->modalDescription('WARNING: Force Deleting a team will permanently remove all associated data, including users, xlsforms, and any survey data collected through this platform. This action is irreversible. Please ensure that you have backed up any important data before proceeding. Are you sure you would like to force delete this team?'),
                Tables\Actions\RestoreAction::make()
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
            'view' => Pages\ViewTeam::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
            InvitesRelationManager::class,
            XlsformsRelationManager::class,
        ];
    }
}
