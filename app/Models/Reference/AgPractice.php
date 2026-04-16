<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgPractice extends Model
{
    protected $table = 'ag_practices';

    public function agPracticeGroup(): BelongsTo
    {
        return $this->belongsTo(AgPracticeGroup::class);
    }
}
