<?php

namespace App\Models\Holpa;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Domain extends Model
{
    protected $table = 'domains';

    protected $guarded = ['id'];

    /** @return HasMany<Theme, $this> */
    public function themes(): HasMany
    {
        return $this->hasMany(Theme::class);
    }

    /** @return HasManyThrough<GlobalIndicator, Theme, $this> */
    public function globalIndicators(): HasManyThrough
    {
        return $this->hasManyThrough(GlobalIndicator::class, Theme::class);
    }

    /** @return HasMany<LocalIndicator, $this> */
    public function localIndicators(): HasMany
    {
        return $this->hasMany(LocalIndicator::class);
    }
}
