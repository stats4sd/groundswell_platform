<?php

namespace App\Policies;

use App\Models\Holpa\Theme;
use App\Models\User;

class ThemePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view themes');
    }

    public function view(User $user, Theme $theme): bool
    {
        return $user->can('view themes');
    }

    public function create(User $user): bool
    {
        return $user->can('maintain themes');
    }

    public function update(User $user, Theme $theme): bool
    {
        return $user->can('maintain themes');
    }

    public function delete(User $user, Theme $theme): bool
    {
        return $user->can('maintain themes');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('maintain themes');
    }

    public function restore(User $user, Theme $theme): bool
    {
        return $user->can('maintain themes');
    }

    public function forceDelete(User $user, Theme $theme): bool
    {
        return $user->can('maintain themes');
    }
}
