<?php

namespace App\Models\Holpa;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    protected $table = 'themes';

    protected $guarded = ['id'];

    // there are some themes with same name in different module, show theme name with module name to ease identification
    public function getDisplayNameAttribute(): string
    {
        return $this->name.' ('.$this->module.')';
    }

    public function globalIndicators(): HasMany
    {
        return $this->hasMany(GlobalIndicator::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
