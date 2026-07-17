<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationAnswer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'answers' => 'array',
    ];
}
