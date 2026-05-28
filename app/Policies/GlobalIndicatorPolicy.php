<?php

namespace App\Policies;

use App\Models\Holpa\GlobalIndicator;
use App\Models\User;

class GlobalIndicatorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view global indicators');
    }

    public function view(User $user, GlobalIndicator $globalIndicator): bool
    {
        return $user->can('view global indicators');
    }

    public function create(User $user): bool
    {
        return $user->can('maintain global indicators');
    }

    public function update(User $user, GlobalIndicator $globalIndicator): bool
    {
        return $user->can('maintain global indicators');
    }

    public function delete(User $user, GlobalIndicator $globalIndicator): bool
    {
        return $user->can('maintain global indicators');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('maintain global indicators');
    }

    public function restore(User $user, GlobalIndicator $globalIndicator): bool
    {
        return $user->can('maintain global indicators');
    }

    public function forceDelete(User $user, GlobalIndicator $globalIndicator): bool
    {
        return $user->can('maintain global indicators');
    }
}
