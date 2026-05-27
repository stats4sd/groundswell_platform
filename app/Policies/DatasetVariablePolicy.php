<?php

namespace App\Policies;

use App\Models\User;
use Stats4sd\FilamentOdkLink\Models\OdkLink\DatasetVariable;

class DatasetVariablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view datasets');
    }

    public function view(User $user, DatasetVariable $datasetVariable): bool
    {
        return $user->can('view datasets');
    }

    public function create(User $user): bool
    {
        return $user->can('maintain datasets');
    }

    public function update(User $user, DatasetVariable $datasetVariable): bool
    {
        return $user->can('maintain datasets');
    }

    public function delete(User $user, DatasetVariable $datasetVariable): bool
    {
        return $user->can('maintain datasets');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('maintain datasets');
    }
}
