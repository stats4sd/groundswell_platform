<?php

namespace App\Models\SampleFrame;

use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The structural local link for a farm stored as an ODK Central Entity - see
 * docs/plans/odk-entities-farm-crud.md. Only holds columns needed for local joins/dedup
 * (owner, location, team_code) and ODK Central sync bookkeeping (odk_uuid, odk_version).
 * Identifiers/properties are not stored anywhere locally - they live only in ODK Central
 * and are read live via OdkFarmEntityService (no local Entity/EntityValue mirror either -
 * see docs/plans/farm-entities-simplify-and-gps-sync.md).
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
}
