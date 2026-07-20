<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class AcademicYearController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;
        $years = AcademicYear::where('school_id', $schoolId)->orderBy('year', 'desc')->get();
        return $this->successResponse($years, 'Daftar Tahun Ajaran');
    }

    public function store(Request $request)
    {
        $request->validate([
            'year' => 'required|string|max:255',
            'active' => 'boolean'
        ]);

        $schoolId = $request->user()->school_id;

        if ($request->input('active', false)) {
            AcademicYear::where('school_id', $schoolId)->update(['active' => false]);
        }

        $year = AcademicYear::create([
            'school_id' => $schoolId,
            'year' => $request->year,
            'active' => $request->input('active', false),
        ]);

        return $this->successResponse($year, 'Tahun Ajaran berhasil ditambahkan', 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'year' => 'required|string|max:255',
            'active' => 'boolean'
        ]);

        $schoolId = $request->user()->school_id;
        $year = AcademicYear::where('school_id', $schoolId)->findOrFail($id);

        if ($request->input('active', false)) {
            AcademicYear::where('school_id', $schoolId)->where('id', '!=', $id)->update(['active' => false]);
        }

        $year->update($request->only(['year', 'active']));

        return $this->successResponse($year, 'Tahun Ajaran berhasil diperbarui');
    }

    public function destroy(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;
        $year = AcademicYear::where('school_id', $schoolId)->findOrFail($id);
        
        if ($year->active) {
            return $this->errorResponse('Tahun ajaran aktif tidak dapat dihapus', 400);
        }

        $year->delete();
        return $this->successResponse(null, 'Tahun Ajaran berhasil dihapus');
    }
}
