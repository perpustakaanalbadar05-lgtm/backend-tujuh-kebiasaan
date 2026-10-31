<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function admin()
    {
        return $this->hasOne(User::class)->where('role', 'admin');
    }

    public function academicYears()
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function classes()
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function teachers()
    {
        return $this->hasMany(Teacher::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public static function initializeDefaults($schoolId, $userId = null)
    {
        // 1. Academic Year & Semester
        if (!AcademicYear::where('school_id', $schoolId)->exists()) {
            $currentYear = date('Y');
            $nextYear = $currentYear + 1;
            $academicYear = AcademicYear::create([
                'school_id' => $schoolId,
                'year' => "{$currentYear}/{$nextYear}",
                'active' => true,
                'created_by' => $userId,
            ]);

            Semester::create([
                'school_id' => $schoolId,
                'academic_year_id' => $academicYear->id,
                'semester' => 'Ganjil',
                'active' => true,
                'created_by' => $userId,
            ]);

            Semester::create([
                'school_id' => $schoolId,
                'academic_year_id' => $academicYear->id,
                'semester' => 'Genap',
                'active' => false,
                'created_by' => $userId,
            ]);
        }

        // 2. Habits (7 Kebiasaan Anak Indonesia Hebat)
        if (!Habit::where('school_id', $schoolId)->exists()) {
            $defaultHabits = [
                ['name' => 'Bangun Pagi', 'order' => 1, 'start' => '04:00', 'end' => '06:30'],
                ['name' => 'Beribadah', 'order' => 2, 'start' => null, 'end' => null],
                ['name' => 'Berolahraga', 'order' => 3, 'start' => '05:30', 'end' => '17:30'],
                ['name' => 'Makan Sehat dan Bergizi', 'order' => 4, 'start' => '06:00', 'end' => '20:00'],
                ['name' => 'Gemar Belajar', 'order' => 5, 'start' => '07:00', 'end' => '21:00'],
                ['name' => 'Bermasyarakat', 'order' => 6, 'start' => null, 'end' => null],
                ['name' => 'Tidur Cepat', 'order' => 7, 'start' => '20:00', 'end' => '22:30'],
            ];

            foreach ($defaultHabits as $h) {
                Habit::create([
                    'school_id' => $schoolId,
                    'name' => $h['name'],
                    'order_number' => $h['order'],
                    'active' => true,
                    'start_time' => $h['start'],
                    'end_time' => $h['end'],
                    'created_by' => $userId,
                ]);
            }
        }

        // 3. Predicates
        if (!Predicate::where('school_id', $schoolId)->exists()) {
            $defaultPredicates = [
                ['name' => 'Belum Terbiasa', 'min' => 0, 'max' => 49],
                ['name' => 'Mulai Terbiasa', 'min' => 50, 'max' => 69],
                ['name' => 'Terbiasa', 'min' => 70, 'max' => 89],
                ['name' => 'Sangat Terbiasa', 'min' => 90, 'max' => 100],
            ];

            foreach ($defaultPredicates as $p) {
                Predicate::create([
                    'school_id' => $schoolId,
                    'name' => $p['name'],
                    'min_score' => $p['min'],
                    'max_score' => $p['max'],
                    'created_by' => $userId,
                ]);
            }
        }
    }
}
