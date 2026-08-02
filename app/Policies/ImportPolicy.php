<?php

namespace App\Policies;

use App\Models\Import;
use App\Models\User;

/**
 * Both kinds of Import - locations and farms - are started from under Survey Locations, so
 * one permission governs the whole history rather than a list that is half-visible depending
 * on which import a row happens to be.
 */
class ImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view survey locations');
    }

    public function view(User $user, Import $import): bool
    {
        return $user->can('view survey locations');
    }
}
