<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    protected $table = 'exchange_rates';

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
