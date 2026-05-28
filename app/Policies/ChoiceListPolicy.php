<?php

namespace App\Policies;

use App\Models\User;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;

class ChoiceListPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view choice lists');
    }

    public function view(User $user, ChoiceList $choiceList): bool
    {
        return $user->can('view choice lists');
    }

    public function create(User $user): bool
    {
        return $user->can('maintain choice lists');
    }

    public function update(User $user, ChoiceList $choiceList): bool
    {
        return $user->can('maintain choice lists');
    }

    public function delete(User $user, ChoiceList $choiceList): bool
    {
        return $user->can('maintain choice lists');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('maintain choice lists');
    }

    public function restore(User $user, ChoiceList $choiceList): bool
    {
        return $user->can('maintain choice lists');
    }

    public function forceDelete(User $user, ChoiceList $choiceList): bool
    {
        return $user->can('maintain choice lists');
    }
}
