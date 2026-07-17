<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class SchoolClassController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;
        
        // Ambil tahun ajaran aktif jika tidak diprovide filter
        $academicYearId = $request->query('academic_year_id');
        if (!$academicYearId) {
            $activeYear = AcademicYear::where('school_id', $schoolId)->where('active', true)->first();
            $academicYearId = $activeYear ? $activeYear->id : null;
        }

        $query = SchoolClass::where('school_id', $schoolId)->with(['academicYear', 'teacher']);
        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        $classes = $query->orderBy('grade')->orderBy('name')->get();
        return $this->successResponse($classes, 'Daftar Kelas');
    }

    public function store(Request $request)
    {
        $request->validate([
            'grade' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'academic_year_id' => 'required|exists:academic_years,id',
            'teacher_id' => 'nullable|exists:teachers,id'
        ]);

        $schoolClass = SchoolClass::create([
            'school_id' => $request->user()->school_id,
            'academic_year_id' => $request->academic_year_id,
            'teacher_id' => $request->teacher_id,
            'grade' => $request->grade,
            'name' => $request->name,
        ]);

        return $this->successResponse($schoolClass, 'Kelas berhasil ditambahkan', 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'grade' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'academic_year_id' => 'required|exists:academic_years,id',
            'teacher_id' => 'nullable|exists:teachers,id'
        ]);

        $schoolClass = SchoolClass::where('school_id', $request->user()->school_id)->findOrFail($id);
        $schoolClass->update($request->only(['grade', 'name', 'academic_year_id', 'teacher_id']));

        return $this->successResponse($schoolClass, 'Kelas berhasil diperbarui');
    }

    public function destroy(Request $request, $id)
    {
        $schoolClass = SchoolClass::where('school_id', $request->user()->school_id)->findOrFail($id);
        $schoolClass->delete();
        
        return $this->successResponse(null, 'Kelas berhasil dihapus');
    }
}
