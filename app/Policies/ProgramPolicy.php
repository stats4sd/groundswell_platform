<?php

namespace App\Policies;

use App\Models\User;
use Filament\Facades\Filament;
use Stats4sd\FilamentTeamManagement\Models\Program;

class ProgramPolicy
{
    private function isProgramPanel(): bool
    {
        return Filament::getCurrentOrDefaultPanel()?->getId() === 'program';
    }

    // view permissions cover both panels:
    // - 'view programs'    → Admin Panel (Global Viewer, Super Admin)
    // - 'view my program'  → Program Admin Panel (Program Admin, Program Viewer)
    public function viewAny(User $user): bool
    {
        if ($this->isProgramPanel()) {
            return $user->can('view my program');
        }

        return $user->can('view programs');
    }

    public function view(User $user, Program $program): bool
    {
        if ($this->isProgramPanel()) {
            return $user->can('view my program');
        }

        return $user->can('view programs');
    }

    // create/delete/restore/forceDelete are Admin Panel-only operations
    public function create(User $user): bool
    {
        return $user->can('maintain programs');
    }

    // update covers both panels:
    // - 'maintain programs'    → Admin Panel (Super Admin)
    // - 'maintain my program'  → Program Admin Panel (Program Admin)
    public function update(User $user, Program $program): bool
    {
        if ($this->isProgramPanel()) {
            return $user->can('maintain my program');
        }

        return $user->can('maintain programs');
    }

    public function delete(User $user, Program $program): bool
    {
        return $user->can('maintain programs');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('maintain programs');
    }

    public function restore(User $user, Program $program): bool
    {
        return $user->can('maintain programs');
    }

    public function forceDelete(User $user, Program $program): bool
    {
        return $user->can('maintain programs');
    }
}
