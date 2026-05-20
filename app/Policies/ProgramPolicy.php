<?php

namespace App\Policies;

use App\Models\User;
use Stats4sd\FilamentTeamManagement\Models\Program;

class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view programs');
    }

    public function view(User $user, Program $program): bool
    {
        return $user->can('view programs');
    }

    public function create(User $user): bool
    {
        return $user->can('maintain programs');
    }

    public function update(User $user, Program $program): bool
    {
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
