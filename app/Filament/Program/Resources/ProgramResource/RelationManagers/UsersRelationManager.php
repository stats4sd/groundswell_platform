<?php

namespace App\Filament\Program\Resources\ProgramResource\RelationManagers;

use Awcodes\Shout\Components\Shout;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Stats4sd\FilamentTeamManagement\Filament\Program\Resources\ProgramResource\RelationManagers\UsersRelationManager as BaseUsersRelationManager;

class UsersRelationManager extends BaseUsersRelationManager
{
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->headerActions([
                Tables\Actions\Action::make('invite users')
                    ->form([
                        Shout::make('info')
                            ->type('info')
                            ->content('Add the email address(es) of the user(s) you would like to invite to this program. An invitation will be sent to each address.')
                            ->columnSpanFull(),
                        Forms\Components\Repeater::make('users')
                            ->label('Email Addresses to Invite')
                            ->simple(
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                            )
                            ->reorderable(false)
                            ->addActionLabel('Add Another Email Address'),
                    ])
                    ->visible(fn () => auth()->user()->can('maintain my program'))
                    ->action(function (array $data, RelationManager $livewire) {
                        if (!auth()->user()->can('maintain my program')) {
                            abort(403);
                        }

                        $this->handleInvitation($data, $livewire->getOwnerRecord());
                    }),
                Tables\Actions\AttachAction::make()
                    ->label('Add Existing User to program')
                    ->visible(fn () => auth()->user()->can('maintain my program')),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()->label('Remove User')
                    ->modalSubmitActionLabel('Remove User')
                    ->modalHeading('Remove User from Program')
                    ->visible(fn () => auth()->user()->can('maintain my program')),
            ]);
    }
}
