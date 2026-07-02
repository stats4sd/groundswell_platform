<?php

namespace App\Models\SampleFrame;

use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;

/**
 * The structural local link for a farm stored as an ODK Central Entity - see
 * docs/plans/odk-entities-farm-crud.md. Only holds columns needed for local joins/dedup
 * (owner, location, team_code) and ODK Central sync bookkeeping (odk_uuid, odk_version).
 * Identifiers/properties are not stored here - they live in ODK Central and are read
 * live via OdkFarmEntityService rather than cached on this model.
 */
class FarmEntity extends Model
{
    use SoftDeletes;

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'altitude' => 'integer',
        'accuracy' => 'float',
    ];

    /** @return BelongsTo<Team, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return MorphOne<Entity, $this> */
    public function entity(): MorphOne
    {
        return $this->morphOne(Entity::class, 'model');
    }
}
