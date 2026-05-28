<?php

namespace App\Policies;

use App\Models\SampleFrame\Farm;
use App\Models\User;

class FarmPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view list of farms');
    }

    public function view(User $user, Farm $farm): bool
    {
        return $user->can('view list of farms');
    }

    public function create(User $user): bool
    {
        return $user->can('maintain list of farms');
    }

    public function update(User $user, Farm $farm): bool
    {
        return $user->can('maintain list of farms');
    }

    public function delete(User $user, Farm $farm): bool
    {
        return $user->can('maintain list of farms');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('maintain list of farms');
    }

    public function restore(User $user, Farm $farm): bool
    {
        return $user->can('maintain list of farms');
    }

    public function forceDelete(User $user, Farm $farm): bool
    {
        return $user->can('maintain list of farms');
    }
}
