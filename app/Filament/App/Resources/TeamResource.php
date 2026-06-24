<?php

namespace App\Filament\App\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use App\Filament\App\Resources\TeamResource\Pages\ListTeams;
use App\Filament\App\Resources\TeamResource\Pages\CreateTeam;
use App\Filament\App\Resources\TeamResource\Pages\EditTeam;
use App\Filament\App\Resources\TeamResource\Pages\ViewTeam;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\App\Resources\TeamResource\Pages;
use App\Filament\App\Resources\TeamResource\RelationManagers\InvitesRelationManager;
use App\Filament\App\Resources\TeamResource\RelationManagers\UsersRelationManager;

// filament-odk-link package related code are commented as some applications may not require ODK functionalities.
// Please uncomment those code if filament-odk-link package is required and added to main repo.

class TeamResource extends Resource
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    // Teams are top-level entities — not children of the current tenant team.
    // Without this, Filament tries to associate a new team with the tenant via a
    // non-existent self-referential 'teams' relationship and throws an exception.
    protected static bool $isScopedToTenant = false;

    public static function getModel(): string
    {
        return config('filament-team-management.models.team');
    }

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(fn () => t('Team Details'))
                    ->schema([
                        TextInput::make('name')
                            ->label(fn () => t('Name'))
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label(fn () => t('Description'))
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(t('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('programs.name')
                    ->label(t('Program'))
                    ->searchable()
                    ->badge()
                    ->color('success')
                    ->visible(config('filament-team-management.use_programs')),
                TextColumn::make('users_count')
                    ->label(fn() => t('# Users'))
                    ->counts('users')
                    ->sortable(),
                TextColumn::make('invites_count')
                    ->label(fn() => t('# Invites'))
                    ->counts('invites')
                    ->sortable(),
                // Tables\Columns\TextColumn::make('xlsforms_count')
                //     ->label('# Xlsforms')
                //     ->counts('xlsforms')
                //     ->sortable(),
                TextColumn::make('created_at')
                    ->label(t('Created At'))
                    ->sortable(),
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
            // XlsformsRelationManager::class,
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('description')->hiddenLabel(),
            ]);
    }
}
