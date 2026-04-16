<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgPracticeGroup extends Model
{
    protected $table = 'ag_practice_groups';

    public function agPractices(): HasMany
    {
        return $this->hasMany(AgPractice::class);
    }

    public function climateMitigationScores(): HasMany
    {
        return $this->hasMany(ClimateMitigationScore::class);
    }
}
