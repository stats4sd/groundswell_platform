<?php

namespace App\Models\SampleFrame;

use App\Models\Team;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;

class Location extends Model
{
    protected $casts = [
        'properties' => 'collection',
    ];

    protected $touches = [
        'parent',
    ];

    protected static bool $flagOwnerOnWrite = true;

    protected static function booted()
    {
        static::saved(function (self $location) {
            if (! static::$flagOwnerOnWrite) {
                return;
            }

            static::flagOwner($location->owner);
        });

        static::deleted(function (self $location) {
            if (! static::$flagOwnerOnWrite) {
                return;
            }

            static::flagOwner($location->owner);
        });
    }

    /**
     * Marking the owner costs three queries (load the team, update it, update its xlsforms) and
     * `$touches = ['parent']` re-fires `saved` once per ancestor, so a bulk writer creating
     * hundreds of locations pays it hundreds of times for a flag that is idempotent. Suppress it
     * for the batch and call flagOwner() once at the end — see LocationImport::collection().
     */
    public static function withoutFlaggingOwner(callable $callback): mixed
    {
        static::$flagOwnerOnWrite = false;

        try {
            return $callback();
        } finally {
            static::$flagOwnerOnWrite = true;
        }
    }

    public static function flagOwner(Team $owner): void
    {
        $owner->update(['has_updated_locations' => true]);
        $owner->xlsforms()->update(['draft_needs_update' => true]);
    }

    /** @return BelongsTo<Team, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'owner_id');
    }

    /** @return BelongsTo<LocationLevel, $this> */
    public function locationLevel(): BelongsTo
    {
        return $this->belongsTo(LocationLevel::class);
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<FarmEntity, $this> */
    public function farmEntities(): HasMany
    {
        return $this->hasMany(FarmEntity::class);
    }

    // Only the lower level locations have sample units (in this case, farms). E.g. A "district" may not have farms directly attached. But a "village" might.
    public function hasFarms(): Attribute
    {
        return new Attribute(
            get: fn (): bool => $this->locationLevel?->has_households ?? false,
        );
    }

    // TODO: consider refactoring this to avoid recursion down through the levels. It gets a bit slow when calling for a location at 3 levels, and might be unmanagaable at 4+ levels.

    // Counts all farms within this location *and* all children locations - e.g. to get the total number of farms in a district, we find all the locations within the district, and then count all the farms in those locations.
    // Counts farm_entities (the ODK-Central-Entities-backed farms), not the legacy farms table - see docs/plans/odk-entities-farm-crud.md.
    public function farmsAllCount(): Attribute
    {
        return new Attribute(
            get: function () {
                return Cache::remember($this->cacheKey().':farmsAllCount', now()->addMinutes(5), function () {
                    return $this->children->reduce(function ($carry, $location) {
                        return $carry + $location->farms_all_count;
                    }, $this->farmEntities->count());
                });
            }
        );
    }

    // from https://laravel-news.com/laravel-model-caching
    public function cacheKey(): string
    {
        return sprintf(
            '%s/%s-%s',
            $this->getTable(),
            $this->getKey(),
            $this->updated_at,
        );
    }

    public function getCsvContentsForOdk(?WithXlsforms $team = null): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location_level_id' => $this->location_level_id,
            'location_level_name' => $this->locationLevel?->name,
            'parent_id' => $this->parent_id,
            'parent_name' => $this->parent?->name,
            'has_farms' => $this->locationLevel?->has_farms,
            'farms_all_count' => $this->farms_all_count,
        ];
    }
}
