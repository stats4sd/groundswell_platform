<?php

namespace App\Filament\Program\ManageProgram;

use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\Teams\Schemas\TeamForm;
use Stats4sd\FilamentTeamManagement\Filament\Program\Pages\ManageProgram\ManageProgramProjects as BaseManageProgramProjects;

class ManageProgramProjects extends BaseManageProgramProjects
{
    public function table(Table $table): Table
    {
        $canManage = fn (): bool => auth()->user()->can('maintain my program');

        // Re-declare the team (project) actions from the package table with
        // "maintain my program" gating on all mutating actions.
        return parent::table($table)
            ->headerActions([
                CreateAction::make()
                    ->schema(TeamForm::getFormSchema())
                    ->visible($canManage),
                AttachAction::make('Add Existing Projects')
                    ->recordTitleAttribute('name')
                    ->multiple()
                    ->visible($canManage),
            ])
            ->recordActions([
                DetachAction::make()->visible($canManage),
                EditAction::make()
                    ->schema(TeamForm::getFormSchema())
                    ->visible($canManage),
            ]);
    }
}
