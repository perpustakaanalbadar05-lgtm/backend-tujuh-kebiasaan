<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
