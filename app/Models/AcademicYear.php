<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\Auditable;

class AcademicYear extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'is_active',
    ];
}
