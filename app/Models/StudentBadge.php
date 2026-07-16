<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentBadge extends Model
{
    protected $fillable = [
        'student_id',
        'badge_id',
        'awarded_at',
        'awarded_by',
    ];

    protected $casts = [
        'awarded_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }

    public function awardedBy()
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}
