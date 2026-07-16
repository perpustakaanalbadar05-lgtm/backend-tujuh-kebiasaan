<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'school_id',
        'name',
        'description',
        'icon',
        'condition_type',
        'condition_value',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'condition_value' => 'integer',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_badges')
                    ->withPivot('awarded_at', 'awarded_by')
                    ->withTimestamps();
    }
}
