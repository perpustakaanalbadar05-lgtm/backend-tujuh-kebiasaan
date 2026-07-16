<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class SemesterController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $query = Semester::with('academicYear')->where('school_id', $schoolId);

        if ($request->has('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        $semesters = $query->orderByDesc('active')->orderByDesc('created_at')->paginate(15);
        return $this->successResponse($semesters, 'Data semester berhasil diambil');
    }

    public function store(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'semester' => 'required|string|in:Ganjil,Genap',
        ]);

        $semester = Semester::create([
            'school_id' => $schoolId,
            'academic_year_id' => $request->academic_year_id,
            'semester' => $request->semester,
            'active' => false,
            'created_by' => $request->user()->id,
        ]);

        return $this->successResponse($semester->load('academicYear'), 'Semester berhasil ditambahkan', 201);
    }

    public function show(Request $request, $id)
    {
        $semester = Semester::with('academicYear')
            ->where('school_id', $request->user()->school_id)
            ->find($id);

        if (!$semester) {
            return $this->errorResponse('Semester tidak ditemukan', 404);
        }

        return $this->successResponse($semester, 'Detail semester berhasil diambil');
    }

    public function update(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;
        $semester = Semester::where('school_id', $schoolId)->find($id);

        if (!$semester) {
            return $this->errorResponse('Semester tidak ditemukan', 404);
        }

        $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'semester' => 'required|string|in:Ganjil,Genap',
        ]);

        $semester->update([
            'academic_year_id' => $request->academic_year_id,
            'semester' => $request->semester,
            'updated_by' => $request->user()->id,
        ]);

        return $this->successResponse($semester->load('academicYear'), 'Semester berhasil diperbarui');
    }

    public function destroy(Request $request, $id)
    {
        $semester = Semester::where('school_id', $request->user()->school_id)->find($id);

        if (!$semester) {
            return $this->errorResponse('Semester tidak ditemukan', 404);
        }

        $semester->delete();
        return $this->successResponse(null, 'Semester berhasil dihapus');
    }

    public function activate(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;
        $semester = Semester::where('school_id', $schoolId)->find($id);

        if (!$semester) {
            return $this->errorResponse('Semester tidak ditemukan', 404);
        }

        DB::beginTransaction();
        try {
            // Nonaktifkan semua semester sekolah ini
            Semester::where('school_id', $schoolId)->update(['active' => false]);
            // Aktifkan semester yang dipilih
            $semester->update(['active' => true, 'updated_by' => $request->user()->id]);

            DB::commit();
            return $this->successResponse($semester->load('academicYear'), 'Semester berhasil diaktifkan');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal mengaktifkan semester: ' . $e->getMessage(), 500);
        }
    }
}
