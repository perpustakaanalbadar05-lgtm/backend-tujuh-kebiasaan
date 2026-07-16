<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class MappingController extends Controller
{
    use ApiResponse;

    /**
     * Get teacher-class mappings for the school.
     */
    public function teacherClasses(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $mappings = DB::table('class_teacher')
            ->join('teachers', 'class_teacher.teacher_id', '=', 'teachers.id')
            ->join('classes', 'class_teacher.class_id', '=', 'classes.id')
            ->where('teachers.school_id', $schoolId)
            ->select(
                'class_teacher.id',
                'class_teacher.class_id',
                'class_teacher.teacher_id',
                'teachers.name as teacher_name',
                'teachers.nip',
                'classes.name as class_name',
                'classes.grade'
            )
            ->orderBy('classes.name')
            ->get();

        return $this->successResponse($mappings, 'Mapping guru-kelas berhasil diambil');
    }

    /**
     * Assign a teacher to a class.
     */
    public function assignTeacherClass(Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'class_id' => 'required|exists:classes,id',
        ]);

        // Check if already mapped
        $exists = DB::table('class_teacher')
            ->where('teacher_id', $request->teacher_id)
            ->where('class_id', $request->class_id)
            ->exists();

        if ($exists) {
            return $this->errorResponse('Guru sudah dipetakan ke kelas ini', 409);
        }

        DB::table('class_teacher')->insert([
            'teacher_id' => $request->teacher_id,
            'class_id' => $request->class_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->successResponse(null, 'Guru berhasil dipetakan ke kelas', 201);
    }

    /**
     * Remove teacher-class mapping.
     */
    public function removeTeacherClass($id)
    {
        $deleted = DB::table('class_teacher')->where('id', $id)->delete();

        if (!$deleted) {
            return $this->errorResponse('Mapping tidak ditemukan', 404);
        }

        return $this->successResponse(null, 'Mapping guru-kelas berhasil dihapus');
    }

    /**
     * Get parent-student mappings for the school.
     */
    public function parentStudents(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $mappings = DB::table('student_parents')
            ->join('parents', 'student_parents.parent_id', '=', 'parents.id')
            ->join('students', 'student_parents.student_id', '=', 'students.id')
            ->where('parents.school_id', $schoolId)
            ->select(
                'student_parents.id',
                'student_parents.student_id',
                'student_parents.parent_id',
                'student_parents.relationship',
                'parents.name as parent_name',
                'students.name as student_name',
                'students.nis'
            )
            ->orderBy('students.name')
            ->get();

        return $this->successResponse($mappings, 'Mapping orang tua-siswa berhasil diambil');
    }

    /**
     * Assign a parent to a student.
     */
    public function assignParentStudent(Request $request)
    {
        $request->validate([
            'parent_id' => 'required|exists:parents,id',
            'student_id' => 'required|exists:students,id',
            'relationship' => 'nullable|string|in:Ayah,Ibu,Wali',
        ]);

        $exists = DB::table('student_parents')
            ->where('parent_id', $request->parent_id)
            ->where('student_id', $request->student_id)
            ->exists();

        if ($exists) {
            return $this->errorResponse('Orang tua sudah dipetakan ke siswa ini', 409);
        }

        DB::table('student_parents')->insert([
            'parent_id' => $request->parent_id,
            'student_id' => $request->student_id,
            'relationship' => $request->relationship ?? 'Wali',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->successResponse(null, 'Orang tua berhasil dipetakan ke siswa', 201);
    }

    /**
     * Remove parent-student mapping.
     */
    public function removeParentStudent($id)
    {
        $deleted = DB::table('student_parents')->where('id', $id)->delete();

        if (!$deleted) {
            return $this->errorResponse('Mapping tidak ditemukan', 404);
        }

        return $this->successResponse(null, 'Mapping orang tua-siswa berhasil dihapus');
    }
}
