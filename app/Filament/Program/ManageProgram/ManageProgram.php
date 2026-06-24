<?php

namespace App\Filament\Program\ManageProgram;

use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Stats4sd\FilamentTeamManagement\Filament\Program\Pages\ManageProgram\ManageProgram as BaseManageProgram;
use Stats4sd\FilamentTeamManagement\Filament\Program\Pages\ManageProgram\ManageProgramInvites;

// Replaces the old Program-panel ProgramResource. The package now manages a program
// via this EditTenantProfile page; we override content() to swap in our own
// Members/Projects widgets that re-apply the "maintain my program" permission gating.
class ManageProgram extends BaseManageProgram
{
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        $this->getFormContentComponent(),
                    ]),
                Tabs::make('User Management')
                    ->contained(false)
                    ->tabs([
                        Tab::make('Projects')
                            ->schema([
                                Livewire::make(ManageProgramProjects::class)
                                    ->key('manage-program-projects'),
                            ]),
                        Tab::make('Members')
                            ->schema([
                                Livewire::make(ManageProgramMembers::class)
                                    ->key('manage-program-members'),
                            ]),
                        Tab::make('Invites')
                            ->schema([
                                Livewire::make(ManageProgramInvites::class)
                                    ->key('manage-program-invites'),
                            ]),
                    ]),
            ]);
    }
}
