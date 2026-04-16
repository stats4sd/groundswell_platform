<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $table = 'currencies';

    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class);
    }
}
