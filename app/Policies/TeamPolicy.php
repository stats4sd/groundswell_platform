<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

class TeamPolicy
{
    private function isAppPanel(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'app';
    }

    public function viewAny(User $user): bool
    {
        if ($this->isAppPanel()) {
            return $user->can('view my team');
        }

        return $user->can('view teams');
    }

    public function view(User $user, Team $team): bool
    {
        if ($this->isAppPanel()) {
            return $user->can('view my team');
        }

        return $user->can('view teams');
    }

    // create/delete/restore/forceDelete are Admin Panel-only operations
    public function create(User $user): bool
    {
        return $user->can('maintain teams');
    }

    public function update(User $user, Team $team): bool
    {
        if ($this->isAppPanel()) {
            return $user->can('maintain my team');
        }

        return $user->can('maintain teams');
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->can('maintain teams');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('maintain teams');
    }

    public function restore(User $user, Team $team): bool
    {
        return $user->can('maintain teams');
    }

    public function forceDelete(User $user, Team $team): bool
    {
        return $user->can('maintain teams');
    }
}
