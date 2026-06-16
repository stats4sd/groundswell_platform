<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Models\Team;
use Awcodes\Shout\Components\Shout;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Spatie\Permission\Models\Role;
use Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\UserResource\Pages\ListUsers as BaseListUsers;
use Stats4sd\FilamentTeamManagement\Models\Program;
use Stats4sd\FilamentTeamManagement\Models\User;

class ListUsers extends BaseListUsers
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return collect(parent::getHeaderActions())
            ->map(function ($action) {
                if ($action->getName() === 'invite users') {
                    $action
                        ->visible(fn () => auth()->user()->can('maintain users'))
                        ->form([
                            Shout::make('info')
                                ->type('info')
                                ->content('Add the email address(es) of the user(s) you would like to invite with a role. An invitation will be sent to each address.')
                                ->columnSpanFull(),
                            Forms\Components\Repeater::make('users')
                                ->label('Email Addresses to Invite')
                                ->schema([
                                    Forms\Components\TextInput::make('email')
                                        ->email()
                                        ->required(),

                                    Forms\Components\Select::make('role')
                                        ->relationship('roles', 'name')
                                        ->live()
                                        ->required(),

                                    Forms\Components\Select::make('program_id')
                                        ->label('Program')
                                        ->options(fn () => Program::query()->pluck('name', 'id'))
                                        ->searchable()
                                        ->required()
                                        ->visible(fn (Forms\Get $get) => in_array(
                                            Role::find($get('role'))?->name,
                                            ['Program Admin', 'Program Viewer'],
                                        )),

                                    Forms\Components\Select::make('team_id')
                                        ->label('Team')
                                        ->options(fn () => Team::query()->pluck('name', 'id'))
                                        ->searchable()
                                        ->required()
                                        ->visible(fn (Forms\Get $get) => Role::find($get('role'))?->name === 'Team Admin'),
                                ])
                                ->reorderable(false)
                                ->addActionLabel('Add Another Email Address'),
                        ])
                        ->action(fn (array $data, ListRecords $livewire) => $this->handleInvitation($data));
                }

                return $action;
            })
            ->all();
    }

    public function handleInvitation(array $data): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(500, 'The user model does not extend the model provided by this package.');
        }

        $user->sendInvites($data['users']);
    }
}
