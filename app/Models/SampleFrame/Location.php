<?php

namespace App\Models\SampleFrame;

use App\Models\Team;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected static function booted()
    {
        static::saved(function (self $location) {
           $location->owner->xlsforms()->update(['draft_needs_update' => true]);
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'owner_id');
    }

    public function locationLevel(): BelongsTo
    {
        return $this->belongsTo(LocationLevel::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function farms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Farm::class);
    }

    // Only the lower level locations have sample units (in this case, farms). E.g. A "district" may not have farms directly attached. But a "village" might.
    public function hasFarms(): Attribute
    {
        return new Attribute(
            get: fn(): bool => $this->locationLevel?->has_households ?? false,
        );
    }

    // TODO: consider refactoring this to avoid recursion down through the levels. It gets a bit slow when calling for a location at 3 levels, and might be unmanagaable at 4+ levels.

    // Counts all farms within this location *and* all children locations - e.g. to get the total number of farms in a district, we find all the locations within the district, and then count all the farms in those locations.
    public function farmsAllCount(): Attribute
    {
        return new Attribute(
            get: function () {
                return Cache::remember($this->cacheKey() . ':farmsAllCount', now()->addMinutes(5), function () {
                    return $this->children->reduce(function ($carry, $location) {
                        return $carry + $location->farms_all_count;
                    }, $this->farms->count());
                });
            }
        );
    }

    public function farmsHouseholdCompleteCount(): Attribute
    {
        return new Attribute(
            get: function () {
                return Cache::remember($this->cacheKey() . ':farmsHouseholdCompleteCount', now()->addMinutes(5), function () {
                    return $this->children->reduce(function ($carry, $location) {
                        return $carry + $location->farms_household_complete_count;
                    }, $this->farms()->where('household_form_completed', true)->count());
                });
            }
        );
    }

    public function farmsFieldworkCompleteCount(): Attribute
    {
        return new Attribute(
            get: function () {
                return Cache::remember($this->cacheKey() . ':farmsFieldworkCompleteCount', now()->addMinutes(5), function () {
                    return $this->children->reduce(function ($carry, $location) {
                        return $carry + $location->farms_fieldwork_complete_count;
                    }, $this->farms()->where('fieldwork_form_completed', true)->count());
                });
            }
        );
    }

    public function farmsAllCompleteCount(): Attribute
    {
        return new Attribute(
            get: function () {
                return Cache::remember($this->cacheKey() . ':farmsAllCompleteCount', now()->addMinutes(5), function () {
                    return $this->children->reduce(function ($carry, $location) {
                        return $carry + $location->farms_all_complete_count;
                    }, $this->farms()
                        ->where('household_form_completed', true)
                        ->where('fieldwork_form_completed', true)
                        ->count());
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
