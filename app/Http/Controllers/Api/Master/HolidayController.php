<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class HolidayController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $query = Holiday::where('school_id', $schoolId);

        if ($request->has('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $holidays = $query->orderBy('date', 'desc')->paginate(15);
        return $this->successResponse($holidays, 'Data hari libur berhasil diambil');
    }

    public function store(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'type' => 'nullable|string|in:nasional,sekolah,khusus',
        ]);

        $holiday = Holiday::create([
            'school_id' => $schoolId,
            'title' => $request->title,
            'date' => $request->date,
            'type' => $request->type ?? 'sekolah',
            'created_by' => $request->user()->id,
        ]);

        return $this->successResponse($holiday, 'Hari libur berhasil ditambahkan', 201);
    }

    public function show(Request $request, $id)
    {
        $holiday = Holiday::where('school_id', $request->user()->school_id)->find($id);

        if (!$holiday) {
            return $this->errorResponse('Hari libur tidak ditemukan', 404);
        }

        return $this->successResponse($holiday, 'Detail hari libur berhasil diambil');
    }

    public function update(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;
        $holiday = Holiday::where('school_id', $schoolId)->find($id);

        if (!$holiday) {
            return $this->errorResponse('Hari libur tidak ditemukan', 404);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'type' => 'nullable|string|in:nasional,sekolah,khusus',
        ]);

        $holiday->update([
            'title' => $request->title,
            'date' => $request->date,
            'type' => $request->type ?? 'sekolah',
            'updated_by' => $request->user()->id,
        ]);

        return $this->successResponse($holiday, 'Hari libur berhasil diperbarui');
    }

    public function destroy(Request $request, $id)
    {
        $holiday = Holiday::where('school_id', $request->user()->school_id)->find($id);

        if (!$holiday) {
            return $this->errorResponse('Hari libur tidak ditemukan', 404);
        }

        $holiday->delete();
        return $this->successResponse(null, 'Hari libur berhasil dihapus');
    }
}
