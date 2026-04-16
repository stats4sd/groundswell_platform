<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataDictionaryEntry extends Model
{
    protected $table = 'data_dictionary_entries';

    protected $casts = [
        'for_indicators' => 'boolean',
    ];
}
