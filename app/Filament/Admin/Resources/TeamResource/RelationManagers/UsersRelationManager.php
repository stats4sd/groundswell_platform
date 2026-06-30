<?php

namespace App\Filament\Admin\Resources\TeamResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Actions\AttachAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Callout;
use Filament\Tables;
use Filament\Tables\Table;
use Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\Teams\RelationManagers\UsersRelationManager as BaseUsersRelationManager;

class UsersRelationManager extends BaseUsersRelationManager
{
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->headerActions([
                Action::make('invite users')
                    ->schema([
                        Callout::make()
                            ->info()
                            ->description('Add the email address(es) of the user(s) you would like to invite to this team. An invitation will be sent to each address.')
                            ->columnSpanFull(),
                        Repeater::make('users')
                            ->label('Email Addresses to Invite')
                            ->simple(
                                TextInput::make('email')
                                    ->email()
                                    ->required()
                            )
                            ->reorderable(false)
                            ->addActionLabel('Add Another Email Address'),
                    ])
                    ->visible(fn () => auth()->user()->can('maintain teams'))
                    ->action(function (array $data, RelationManager $livewire) {
                        if (!auth()->user()->can('maintain teams')) {
                            abort(403);
                        }

                        $this->handleInvitation($data, $livewire->getOwnerRecord());
                    }),
                AttachAction::make()
                    ->label('Add Existing User to team')
                    ->visible(fn () => auth()->user()->can('maintain teams')),
            ]);
    }
}
