<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\School;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\Habit;
use App\Models\Predicate;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Super Admin
        $superadmin = User::create([
            'name' => 'Super Administrator',
            'username' => 'superadmin',
            'email' => 'superadmin@g7kaih.id',
            'password' => Hash::make('password'),
            'role' => 'superadmin',
            'school_id' => null,
        ]);

        // 2. School
        $school = School::create([
            'name' => 'SDIT Al-Fatih',
            'npsn' => '10203040',
            'email' => 'info@sditalfatih.sch.id',
            'phone' => '021-12345678',
            'address' => 'Jl. Pendidikan No. 1, Jakarta',
            'status' => 'active',
            'created_by' => $superadmin->id,
        ]);

        // 3. Admin Sekolah
        $admin = User::create([
            'name' => 'Admin Al-Fatih',
            'username' => 'admin_alfatih',
            'email' => 'admin@sditalfatih.sch.id',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'school_id' => $school->id,
        ]);

        // 4. Academic Year & Semester
        $academicYear = AcademicYear::create([
            'school_id' => $school->id,
            'year' => '2026/2027',
            'active' => true,
        ]);

        $semester = Semester::create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'semester' => 'Ganjil',
            'active' => true,
        ]);

        // 5. Classes
        $class10A = SchoolClass::create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'Kelas 10-A',
            'grade' => '10',
            'active' => true,
        ]);

        // 6. Teachers
        $teacherUser1 = User::create([
            'name' => 'Budi Santoso, S.Pd',
            'username' => '198001012005011001',
            'email' => 'budi@sditalfatih.sch.id',
            'password' => Hash::make('password'),
            'role' => 'guru',
            'school_id' => $school->id,
        ]);
        $teacher1 = Teacher::create([
            'school_id' => $school->id,
            'user_id' => $teacherUser1->id,
            'nip' => '198001012005011001',
            'name' => $teacherUser1->name,
            'email' => $teacherUser1->email,
        ]);

        // Assign teacher to class (pivot)
        DB::table('class_teacher')->insert([
            'class_id' => $class10A->id,
            'teacher_id' => $teacher1->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 7. Students & Parents (5 Dummy)
        for ($i = 1; $i <= 5; $i++) {
            // Student User
            $studentUser = User::create([
                'name' => "Siswa Dummy $i",
                'username' => "10010$i",
                'email' => "siswa$i@sditalfatih.sch.id",
                'password' => Hash::make('password'),
                'role' => 'siswa',
                'school_id' => $school->id,
            ]);

            $student = Student::create([
                'school_id' => $school->id,
                'user_id' => $studentUser->id,
                'class_id' => $class10A->id,
                'nis' => "10010$i",
                'nisn' => "005123450$i",
                'name' => $studentUser->name,
                'gender' => $i % 2 == 0 ? 'P' : 'L',
                'status' => 'active',
            ]);

            // Parent User
            $parentUser = User::create([
                'name' => "Orang Tua Siswa $i",
                'username' => "ortu$i",
                'email' => "ortu$i@gmail.com",
                'password' => Hash::make('password'),
                'role' => 'orangtua',
                'school_id' => $school->id,
            ]);

            $parent = StudentParent::create([
                'school_id' => $school->id,
                'user_id' => $parentUser->id,
                'name' => $parentUser->name,
                'email' => $parentUser->email,
            ]);

            // Pivot Student - Parent
            DB::table('student_parents')->insert([
                'student_id' => $student->id,
                'parent_id' => $parent->id,
                'relationship' => 'Ayah',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 8. Configuration: Habits
        $habits = [
            'Bangun Pagi', 'Beribadah', 'Berolahraga', 
            'Makan Sehat dan Bergizi', 'Gemar Belajar', 
            'Bermasyarakat', 'Tidur Cepat'
        ];
        foreach ($habits as $index => $habitName) {
            Habit::create([
                'school_id' => $school->id,
                'name' => $habitName,
                'order_number' => $index + 1,
                'active' => true,
            ]);
        }

        // 9. Configuration: Predicates
        $predicates = [
            ['name' => 'Belum Terbiasa', 'min' => 0, 'max' => 49],
            ['name' => 'Mulai Terbiasa', 'min' => 50, 'max' => 69],
            ['name' => 'Terbiasa', 'min' => 70, 'max' => 89],
            ['name' => 'Sangat Terbiasa', 'min' => 90, 'max' => 100],
        ];
        foreach ($predicates as $pred) {
            Predicate::create([
                'school_id' => $school->id,
                'name' => $pred['name'],
                'min_score' => $pred['min'],
                'max_score' => $pred['max'],
            ]);
        }
    }
}
