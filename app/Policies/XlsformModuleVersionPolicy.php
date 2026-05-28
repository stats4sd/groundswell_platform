<?php

namespace App\Policies;

use App\Models\User;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

// This policy covers both DietDiversityModuleVersionResource and XlsformModuleVersionResource,
// as both resources share the XlsformModuleVersion model.
class XlsformModuleVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view xlsform module versions');
    }

    public function view(User $user, XlsformModuleVersion $xlsformModuleVersion): bool
    {
        return $user->can('view xlsform module versions');
    }

    public function create(User $user): bool
    {
        return $user->can('maintain xlsform module versions');
    }

    public function update(User $user, XlsformModuleVersion $xlsformModuleVersion): bool
    {
        return $user->can('maintain xlsform module versions');
    }

    public function delete(User $user, XlsformModuleVersion $xlsformModuleVersion): bool
    {
        return $user->can('maintain xlsform module versions');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('maintain xlsform module versions');
    }

    public function restore(User $user, XlsformModuleVersion $xlsformModuleVersion): bool
    {
        return $user->can('maintain xlsform module versions');
    }

    public function forceDelete(User $user, XlsformModuleVersion $xlsformModuleVersion): bool
    {
        return $user->can('maintain xlsform module versions');
    }
}
