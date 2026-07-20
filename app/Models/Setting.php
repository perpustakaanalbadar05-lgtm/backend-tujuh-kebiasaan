<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'school_id',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'json',
    ];
}
