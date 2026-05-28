<?php

namespace App\Policies;

use App\Models\User;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class XlsformTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view xlsform templates');
    }

    public function view(User $user, XlsformTemplate $xlsformTemplate): bool
    {
        return $user->can('view xlsform templates');
    }

    public function create(User $user): bool
    {
        return $user->can('maintain xlsform templates');
    }

    public function update(User $user, XlsformTemplate $xlsformTemplate): bool
    {
        return $user->can('maintain xlsform templates');
    }

    public function delete(User $user, XlsformTemplate $xlsformTemplate): bool
    {
        return $user->can('maintain xlsform templates');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('maintain xlsform templates');
    }

    public function restore(User $user, XlsformTemplate $xlsformTemplate): bool
    {
        return $user->can('maintain xlsform templates');
    }

    public function forceDelete(User $user, XlsformTemplate $xlsformTemplate): bool
    {
        return $user->can('maintain xlsform templates');
    }
}
