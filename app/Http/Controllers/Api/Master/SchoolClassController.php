<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SchoolClassController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        $schoolId = $user->school_id;
        if ($user->role === 'superadmin' && $request->has('school_id')) {
            $schoolId = $request->school_id;
        }

        if ($schoolId) {
            School::initializeDefaults($schoolId, $user->id);
        }

        $query = SchoolClass::with(['academicYear', 'teacher']);
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('grade', 'like', "%{$search}%");
            });
        }

        $classes = $query->orderBy('grade')->orderBy('name')->get();
        return $this->successResponse($classes, 'Daftar Kelas');
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $schoolId = $user->school_id ?? $request->school_id;

        if (!$schoolId) {
            return $this->errorResponse('School ID diperlukan', 400);
        }

        School::initializeDefaults($schoolId, $user->id);

        $request->validate([
            'grade' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'academic_year_id' => 'nullable|integer',
            'teacher_id' => [
                'nullable',
                Rule::exists('teachers', 'id')->where('school_id', $schoolId)
            ]
        ]);

        // Auto-resolve academic_year_id to ensure it belongs to this school
        $academicYearId = $request->academic_year_id;
        if ($academicYearId) {
            $valid = AcademicYear::where('school_id', $schoolId)->where('id', $academicYearId)->exists();
            if (!$valid) {
                $academicYearId = null;
            }
        }

        if (!$academicYearId) {
            $activeYear = AcademicYear::where('school_id', $schoolId)->where('active', true)->first();
            if (!$activeYear) {
                $activeYear = AcademicYear::where('school_id', $schoolId)->latest()->first();
            }
            $academicYearId = $activeYear ? $activeYear->id : null;
        }

        if (!$academicYearId) {
            return $this->errorResponse('Tahun ajaran aktif tidak ditemukan untuk sekolah ini', 422);
        }

        $schoolClass = SchoolClass::create([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'teacher_id' => $request->teacher_id,
            'grade' => $request->grade,
            'name' => $request->name,
            'active' => true,
        ]);

        if ($request->teacher_id) {
            DB::table('class_teacher')->updateOrInsert(
                ['class_id' => $schoolClass->id],
                ['teacher_id' => $request->teacher_id, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        return $this->successResponse($schoolClass->load(['academicYear', 'teacher']), 'Kelas berhasil ditambahkan', 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $schoolId = $user->school_id;

        $schoolClass = $user->role === 'superadmin'
            ? SchoolClass::findOrFail($id)
            : SchoolClass::where('school_id', $schoolId)->findOrFail($id);

        $targetSchoolId = $schoolClass->school_id;

        $request->validate([
            'grade' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'academic_year_id' => 'nullable|integer',
            'teacher_id' => [
                'nullable',
                Rule::exists('teachers', 'id')->where('school_id', $targetSchoolId)
            ]
        ]);

        $academicYearId = $request->academic_year_id;
        if ($academicYearId) {
            $valid = AcademicYear::where('school_id', $targetSchoolId)->where('id', $academicYearId)->exists();
            if (!$valid) {
                $academicYearId = $schoolClass->academic_year_id;
            }
        } else {
            $academicYearId = $schoolClass->academic_year_id;
        }

        $schoolClass->update([
            'grade' => $request->grade,
            'name' => $request->name,
            'academic_year_id' => $academicYearId,
            'teacher_id' => $request->teacher_id,
            'active' => $request->has('active') ? $request->boolean('active') : $schoolClass->active,
        ]);

        if ($request->teacher_id) {
            DB::table('class_teacher')->updateOrInsert(
                ['class_id' => $schoolClass->id],
                ['teacher_id' => $request->teacher_id, 'updated_at' => now(), 'created_at' => now()]
            );
        } else {
            DB::table('class_teacher')->where('class_id', $schoolClass->id)->delete();
        }

        return $this->successResponse($schoolClass->load(['academicYear', 'teacher']), 'Kelas berhasil diperbarui');
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $schoolClass = $user->role === 'superadmin'
            ? SchoolClass::findOrFail($id)
            : SchoolClass::where('school_id', $user->school_id)->findOrFail($id);

        DB::table('class_teacher')->where('class_id', $schoolClass->id)->delete();
        $schoolClass->delete();
        
        return $this->successResponse(null, 'Kelas berhasil dihapus');
    }
}
