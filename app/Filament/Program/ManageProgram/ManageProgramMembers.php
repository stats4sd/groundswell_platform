<?php

namespace App\Filament\Program\ManageProgram;

use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Tables\Table;
use Stats4sd\FilamentTeamManagement\Filament\Program\Pages\ManageProgram\ManageProgramMembers as BaseManageProgramMembers;

class ManageProgramMembers extends BaseManageProgramMembers
{
    public function table(Table $table): Table
    {
        $canManage = fn (): bool => auth()->user()->can('maintain my program');

        // Re-declare the member actions from the package table with "maintain my program" gating.
        return parent::table($table)
            ->headerActions([
                Action::make('Invite')
                    ->schema([
                        Callout::make('Invitation')
                            ->info()
                            ->description('Add the email address(es) of the user(s) you would like to invite to this program. An invitation will be sent to each address.')
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
                    ->visible($canManage)
                    ->action(fn (array $data) => Filament::getTenant()->sendInvites($data['users'])),
                AttachAction::make('Add Existing Users')
                    ->recordTitleAttribute('email')
                    ->multiple()
                    ->visible($canManage),
            ])
            ->recordActions([
                DetachAction::make()->visible($canManage),
            ]);
    }
}
