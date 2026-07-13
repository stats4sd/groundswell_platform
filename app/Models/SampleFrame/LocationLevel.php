<?php

namespace App\Models\SampleFrame;

use App\Models\Team;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;

class LocationLevel extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $locationLevel) {
            $locationLevel->slug = $locationLevel->slug ?? Str::slug($locationLevel->name, '_');
        });

        static::saved(function (self $locationLevel) {

            // mark forms as needing a new deployment
            $locationLevel->owner->xlsforms()
                ->update(['draft_needs_update' => true]);
        });

        if (Filament::hasTenancy() && Filament::getTenant() instanceof Team) {
            static::addGlobalScope('team', function ($query) {
                $query->where('owner_id', Filament::getTenant()->id);
            });
        }

    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'owner_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    // the position of the location level in the hierarchy based on the owning team.
    // This is used to find the correct location level to use in the ODK form's repeat group for locations. (NOTE - this assumes that there is a single hierarchy of location levels. Currently untested with more complex setups!)
    public function getPos(): int
    {

        $position = 1;
        $level = $this;

        while ($level->parent_id) {
            $level = $level->parent;
            $position++;
        }

        return $position;
    }

    public function pos(): Attribute
    {
        return new Attribute(
            get: fn () => $this->getPos(),
        );
    }

    // Root-to-leaf chain of this team's location levels, ending at the level farms attach
    // to directly (has_farms = true). Position N in the chain (1-indexed) corresponds to
    // the `loc{N}` convention used in the Farm Registration XLSForm's entities sheet.
    public static function farmLevelChain(Team $team): Collection
    {
        $level = static::where('owner_id', $team->id)->where('has_farms', true)->first();

        if (! $level) {
            return collect();
        }

        $chain = collect([$level]);

        while ($level->parent) {
            $level = $level->parent;
            $chain->prepend($level);
        }

        return $chain;
    }

    public function getCsvContentsForOdk(?WithXlsforms $team = null): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'pos' => $this->pos,
            'has_farms' => $this->has_farms,
        ];
    }
}
