<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Journal extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function details()
    {
        return $this->hasMany(JournalDetail::class);
    }

    public function teacherApproval()
    {
        return $this->hasOne(TeacherApproval::class);
    }

    public function parentApproval()
    {
        return $this->hasOne(ParentApproval::class);
    }
}
