<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stats4sd\FilamentOdkLink\Models\Region;

class ClimateMitigationScore extends Model
{
    protected $table = 'climate_mitigation_scores';

    public function agPractice(): BelongsTo
    {
        return $this->belongsTo(AgPractice::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
