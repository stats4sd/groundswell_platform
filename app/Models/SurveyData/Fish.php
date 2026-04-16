<?php

namespace App\Models\SurveyData;

use App\Models\Interfaces\RepeatModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;

class Fish extends Model implements RepeatModel
{
    protected $table = 'fishes';

    protected $casts = [
        'properties' => 'collection',
    ];

    public function fishUses(): HasMany
    {
        return $this->hasMany(FishUse::class);
    }

    public function farmSurveyData(): BelongsTo
    {
        return $this->belongsTo(FarmSurveyData::class, 'submission_id', 'submission_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'submission_id', 'id');
    }
}
