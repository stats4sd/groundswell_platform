<?php

namespace App\Models;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Rappasoft\LaravelAuthenticationLog\Traits\AuthenticationLoggable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithOdkCentralAccount;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasOdkCentralAccount;
use Stats4sd\FilamentTeamManagement\Models\User as FilamentTeamManagementUser;

class User extends FilamentTeamManagementUser implements WithOdkCentralAccount
{
    use HasOdkCentralAccount;
    use AuthenticationLoggable;

    /**
     * @throws RequestException
     * @throws BindingResolutionException
     * @throws ConnectionException
     */
    public function assignRole(...$roles): void
    {
        parent::assignRole(...$roles);

        if ($this->isAdmin()) {
            $this->syncWithOdkCentral();
        }
    }

    public function teams(): BelongsToMany
    {
        return parent::teams()
            ->using(TeamMembership::class);
    }

    /** @return  HasMany<TeamMembership, $this> */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

}
