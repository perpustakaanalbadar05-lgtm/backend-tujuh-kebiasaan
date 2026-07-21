<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalDetail extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_done' => 'boolean',
    ];

    public function habit()
    {
        return $this->belongsTo(Habit::class);
    }
}
