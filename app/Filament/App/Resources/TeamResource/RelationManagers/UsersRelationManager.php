<?php

namespace App\Filament\App\Resources\TeamResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Callout;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Stats4sd\FilamentTeamManagement\Models\Interfaces\TeamInterface;
use Stats4sd\FilamentTeamManagement\Models\User;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    // turn on Edit mode so that "Add Existing User to team" button will be shown when viewing team record
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Callout::make()
                    ->info()
                    ->description(fn (User $record) => new HtmlString("Edit user's role within this team<br/>$record->name ($record->email)")),
                Forms\Components\Checkbox::make('is_admin')
                    ->label(fn (User $record): string => "$record->name is a Team Admin")
                    ->helperText(t('Team Admins have full access to all team settings and can manage all team members. They can edit or delete data. Non-admins can only collect data and view data.')),
            ])->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                // hide column "is_admin" as team admin is not being used in this application
                // keep below commented code, it will be used in other application
                // Tables\Columns\IconColumn::make('is_admin')
                //     ->label('Is a Team Admin?')
                //     ->boolean(),

                Tables\Columns\TextColumn::make('created_at'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('invite users')
                    ->form([
                        Callout::make()
                            ->info()
                            ->description(t('Add the email address(es) of the user(s) you would like to invite to this team. An invitation will be sent to each address.'))
                            ->columnSpanFull(),
                        Forms\Components\Repeater::make('users')
                            ->label(t('Email Addresses to Invite'))
                            ->simple(
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                            )
                            ->reorderable(false)
                            ->addActionLabel(t('Add Another Email Address')),
                    ])
                    ->visible(fn () => auth()->user()->can('maintain my team'))
                    ->action(function (array $data, RelationManager $livewire) {
                        if (!auth()->user()->can('maintain my team')) {
                            abort(403);
                        }

                        $this->handleInvitation($data, $livewire->getOwnerRecord());
                    }),
                Tables\Actions\AttachAction::make()
                    ->label(fn () => t('Add Existing User to team'))
                    ->visible(fn () => auth()->user()->can('maintain my team')),
            ])
            ->actions([
                // hide "Edit User Role" button as team admin is not being used in this application
                // keep below commented code, it will be used in other application
                // Tables\Actions\EditAction::make()->label('Edit User Role'),

                Tables\Actions\DetachAction::make()->label(fn () => t('Remove User'))
                    ->modalSubmitActionLabel(fn () => t('Remove User'))
                    ->modalHeading(fn () => t('Remove User from Team')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()->label(fn () => t('Remove selected'))
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
