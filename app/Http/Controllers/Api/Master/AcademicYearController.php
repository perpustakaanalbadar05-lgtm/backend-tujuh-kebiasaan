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
        $years = AcademicYear::where('school_id', $schoolId)->orderBy('start_date', 'desc')->get();
        return $this->successResponse($years, 'Daftar Tahun Ajaran');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'active' => 'boolean'
        ]);

        $schoolId = $request->user()->school_id;

        // Jika diset active, nonaktifkan yang lain
        if ($request->input('active', false)) {
            AcademicYear::where('school_id', $schoolId)->update(['active' => false]);
        }

        $year = AcademicYear::create([
            'school_id' => $schoolId,
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'active' => $request->input('active', false),
        ]);

        return $this->successResponse($year, 'Tahun Ajaran berhasil ditambahkan', 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'active' => 'boolean'
        ]);

        $schoolId = $request->user()->school_id;
        $year = AcademicYear::where('school_id', $schoolId)->findOrFail($id);

        if ($request->input('active', false)) {
            AcademicYear::where('school_id', $schoolId)->where('id', '!=', $id)->update(['active' => false]);
        }

        $year->update($request->only(['name', 'start_date', 'end_date', 'active']));

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
