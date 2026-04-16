<?php

namespace App\Models\SurveyData;

use App\Models\Interfaces\RepeatModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;

class EcologicalPractice extends Model implements RepeatModel
{
    protected $table = 'ecological_practices';

    protected $casts = [
        'properties' => 'collection',
    ];

    public function farmSurveyData(): BelongsTo
    {
        return $this->belongsTo(FarmSurveyData::class, 'submission_id', 'submission_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'submission_id', 'id');
    }
}
