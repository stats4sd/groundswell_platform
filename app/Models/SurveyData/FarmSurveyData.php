<?php

namespace App\Models\SurveyData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use App\Models\SampleFrame\Farm;

class FarmSurveyData extends Model
{
    protected $table = 'farm_survey_data';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'properties' => 'collection',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'submission_id', 'id');
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    // Link to repeat groups

    public function crops(): HasMany
    {
        return $this->hasMany(Crop::class, 'farm_survey_data_id', 'id');
    }

    public function livestocks(): HasMany
    {
        return $this->hasMany(Livestock::class, 'farm_survey_data_id', 'id');
    }

}
