<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalDetail extends Model
{
    protected $guarded = ['id'];

    public function habit()
    {
        return $this->belongsTo(Habit::class);
    }
}
