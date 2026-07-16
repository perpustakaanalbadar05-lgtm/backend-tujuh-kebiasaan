<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Predicate extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
