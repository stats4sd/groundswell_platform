<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class TeamMembership extends Pivot
{

    protected static function booted()
    {
        static::created(function (self $teamMembership) {

            // update user on Odk Central
            $odkLinkService = app()->make(OdkLinkService::class);

            $odkLinkService->addUserToProject($teamMembership->user, $teamMembership->team->odkProject);


        });

        static::deleted(function (self $teamMembership) {
            $odkLinkService = app()->make(OdkLinkService::class);

            $odkLinkService->removeUserFromProject($teamMembership->user, $teamMembership->team->odkProject);

        });
    }


    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

}
