<?php

namespace App\Policies;

use App\Models\User;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;

class XlsformModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view xlsform modules');
    }

    public function view(User $user, XlsformModule $xlsformModule): bool
    {
        return $user->can('view xlsform modules');
    }

    public function create(User $user): bool
    {
        return $user->can('maintain xlsform modules');
    }

    public function update(User $user, XlsformModule $xlsformModule): bool
    {
        return $user->can('maintain xlsform modules');
    }

    public function delete(User $user, XlsformModule $xlsformModule): bool
    {
        return $user->can('maintain xlsform modules');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('maintain xlsform modules');
    }

    public function restore(User $user, XlsformModule $xlsformModule): bool
    {
        return $user->can('maintain xlsform modules');
    }

    public function forceDelete(User $user, XlsformModule $xlsformModule): bool
    {
        return $user->can('maintain xlsform modules');
    }
}
