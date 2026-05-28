<?php

namespace App\Policies;

use App\Models\SampleFrame\LocationLevel;
use App\Models\User;

class LocationLevelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view manage location levels');
    }

    public function view(User $user, LocationLevel $locationLevel): bool
    {
        return $user->can('view manage location levels');
    }

    public function create(User $user): bool
    {
        return $user->can('maintain manage location levels');
    }

    public function update(User $user, LocationLevel $locationLevel): bool
    {
        return $user->can('maintain manage location levels');
    }

    public function delete(User $user, LocationLevel $locationLevel): bool
    {
        return $user->can('maintain manage location levels');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('maintain manage location levels');
    }

    public function restore(User $user, LocationLevel $locationLevel): bool
    {
        return $user->can('maintain manage location levels');
    }

    public function forceDelete(User $user, LocationLevel $locationLevel): bool
    {
        return $user->can('maintain manage location levels');
    }
}
