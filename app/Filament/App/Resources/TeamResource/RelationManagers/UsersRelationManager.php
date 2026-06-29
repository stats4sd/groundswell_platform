<?php

namespace App\Filament\App\Resources\TeamResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Checkbox;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Callout;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Model;
use Stats4sd\FilamentTeamManagement\Models\Interfaces\TeamInterface;
use Stats4sd\FilamentTeamManagement\Models\User;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view my team') ?? false;
    }

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->can('maintain my team') ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make()
                    ->info()
                    ->description(fn (User $record) => new HtmlString("Edit user's role within this team<br/>$record->name ($record->email)")),
                Checkbox::make('is_admin')
                    ->label(fn (User $record): string => "$record->name is a Team Admin")
                    ->helperText(t('Team Admins have full access to all team settings and can manage all team members. They can edit or delete data. Non-admins can only collect data and view data.')),
            ])->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                // hide column "is_admin" as team admin is not being used in this application
                // keep below commented code, it will be used in other application
                // Tables\Columns\IconColumn::make('is_admin')
                //     ->label('Is a Team Admin?')
                //     ->boolean(),

                TextColumn::make('created_at'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('invite users')
                    ->schema([
                        Callout::make()
                            ->info()
                            ->description(t('Add the email address(es) of the user(s) you would like to invite to this team. An invitation will be sent to each address.'))
                            ->columnSpanFull(),
                        Repeater::make('users')
                            ->label(t('Email Addresses to Invite'))
                            ->simple(
                                TextInput::make('email')
                                    ->email()
                                    ->required()
                            )
                            ->reorderable(false)
                            ->addActionLabel(t('Add Another Email Address')),
                    ])
                    ->visible(! $this->isReadOnly())
                    ->action(function (array $data, RelationManager $livewire) {
                        if ($this->isReadOnly()) {
                            abort(403);
                        }

                        $this->handleInvitation($data, $livewire->getOwnerRecord());
                    }),
                AttachAction::make()
                    ->label(fn () => t('Add Existing User to team'))
                    ->visible(! $this->isReadOnly()),
            ])
            ->recordActions([
                // hide "Edit User Role" button as team admin is not being used in this application
                // keep below commented code, it will be used in other application
                // Tables\Actions\EditAction::make()->label('Edit User Role'),

                DetachAction::make()->label(fn () => t('Remove User'))
                    ->modalSubmitActionLabel(fn () => t('Remove User'))
                    ->modalHeading(fn () => t('Remove User from Team')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()->label(fn () => t('Remove selected'))
                        ->modalSubmitActionLabel(fn () => t('Remove Selected Users'))
                        ->modalHeading(fn () => t('Remove Selected Users from Team')),
                ]),
            ]);
    }

    public function handleInvitation(array $data, TeamInterface $team): void
    {
        $team->sendInvites($data['users']);
    }
}
