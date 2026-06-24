<?php

namespace App\Filament\Admin\Resources;

use Filament\Tables\Columns\TextColumn;
use Filament\Actions\RestoreAction;
use Filament\Tables\Filters\TrashedFilter;
use App\Filament\Admin\Resources\TeamResource\Pages\ListTeams;
use App\Filament\Admin\Resources\TeamResource\Pages\CreateTeam;
use App\Filament\Admin\Resources\TeamResource\Pages\EditTeam;
use App\Filament\Admin\Resources\TeamResource\Pages\ViewTeam;
use App\Filament\Admin\Resources\TeamResource\Pages;
use App\Filament\Admin\Resources\TeamResource\RelationManagers\XlsformsRelationManager;
use App\Models\Team;
use Filament\Actions\ForceDeleteAction;
use Filament\Tables;
use Filament\Tables\Table;
use Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\Teams\RelationManagers\InvitesRelationManager;
use App\Filament\Admin\Resources\TeamResource\RelationManagers\UsersRelationManager;

class TeamResource extends \Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\Teams\TeamResource
{
    protected static ?string $model = Team::class;

    protected static bool $shouldRegisterNavigation = true;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('programs.name')
                    ->searchable()
                    ->badge()
                    ->color('success')
                    ->visible(config('filament-team-management.use_programs')),
                TextColumn::make('users_count')
                    ->label('# Users')
                    ->counts('users')
                    ->sortable(),
                TextColumn::make('invites_count')
                    ->label('# Invites')
                    ->counts('invites')
                    ->sortable(),
                TextColumn::make('xlsforms_count')
                    ->label('# Xlsforms')
                    ->counts('xlsforms')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->sortable(),
            ])
            ->recordActions([
                ForceDeleteAction::make()
                ->modalDescription('WARNING: Force Deleting a team will permanently remove all associated data, including users, xlsforms, and any survey data collected through this platform. This action is irreversible. Please ensure that you have backed up any important data before proceeding. Are you sure you would like to force delete this team?'),
                RestoreAction::make()
            ])
            ->filters([
                TrashedFilter::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeams::route('/'),
            'create' => CreateTeam::route('/create'),
            'edit' => EditTeam::route('/{record}/edit'),
            'view' => ViewTeam::route('/{record}'),
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
