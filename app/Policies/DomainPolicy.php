<?php

namespace App\Policies;

use App\Models\Holpa\Domain;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DomainPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return ($user->can('view domains'));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Domain $domain): bool
    {
        return ($user->can('view domains'));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return ($user->can('maintain domains'));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Domain $domain): bool
    {
        return ($user->can('maintain domains'));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Domain $domain): bool
    {
        return ($user->can('maintain domains'));
    }

    /**
     * Determine whether the user can bulk delete models.
     */
    public function deleteAny(User $user): bool
    {
        return ($user->can('maintain domains'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Domain $domain): bool
    {
        return ($user->can('maintain domains'));
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Domain $domain): bool
    {
        return ($user->can('maintain domains'));
    }
}
