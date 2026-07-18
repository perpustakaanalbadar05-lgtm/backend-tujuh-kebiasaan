<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\Auditable;

class Habit extends Model
{
    use Auditable;

    protected $guarded = ['id'];
}
