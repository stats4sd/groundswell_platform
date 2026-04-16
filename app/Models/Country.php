<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends \Stats4sd\FilamentOdkLink\Models\Country
{
    /** @return HasMany<PreviousResult, $this> */
    public function tempResults(): HasMany
    {
        return $this->hasMany(PreviousResult::class);
    }
}
