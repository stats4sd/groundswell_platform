<?php

namespace App\Policies;

use App\Models\SampleFrame\FarmEntity;
use App\Models\User;

// Mirrors FarmPolicy's gate names so the same permissions govern both the legacy
// database-backed Farms page and this new ODK-Entities-backed one during coexistence.
class FarmEntityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view list of farms');
    }

    public function view(User $user, FarmEntity $farmEntity): bool
    {
        return $user->can('view list of farms');
    }

    public function create(User $user): bool
    {
        return $user->can('maintain list of farms');
    }

    public function update(User $user, FarmEntity $farmEntity): bool
    {
        return $user->can('maintain list of farms');
    }

    public function delete(User $user, FarmEntity $farmEntity): bool
    {
        return $user->can('maintain list of farms');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('maintain list of farms');
    }

    public function restore(User $user, FarmEntity $farmEntity): bool
    {
        return $user->can('maintain list of farms');
    }

    public function forceDelete(User $user, FarmEntity $farmEntity): bool
    {
        return $user->can('maintain list of farms');
    }
}
