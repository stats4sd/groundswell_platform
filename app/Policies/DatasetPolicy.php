<?php

namespace App\Policies;

use App\Models\User;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;

class DatasetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view datasets');
    }

    public function view(User $user, Dataset $dataset): bool
    {
        return $user->can('view datasets');
    }

    public function create(User $user): bool
    {
        return $user->can('maintain datasets');
    }

    public function update(User $user, Dataset $dataset): bool
    {
        return $user->can('maintain datasets');
    }

    public function delete(User $user, Dataset $dataset): bool
    {
        return $user->can('maintain datasets');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('maintain datasets');
    }

    public function restore(User $user, Dataset $dataset): bool
    {
        return $user->can('maintain datasets');
    }

    public function forceDelete(User $user, Dataset $dataset): bool
    {
        return $user->can('maintain datasets');
    }
}
