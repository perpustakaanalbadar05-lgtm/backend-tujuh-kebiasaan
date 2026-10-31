<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class HabitController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;
        $query = Habit::where('school_id', $schoolId)->orderBy('order_number');

        // Jika yang mengakses adalah siswa/ortu, hanya tampilkan yang aktif
        if (in_array($request->user()->role, ['siswa', 'orangtua'])) {
            $query->where('active', true);
        }

        $habits = $query->get();

        return $this->successResponse($habits, 'Daftar kebiasaan berhasil diambil');
    }

    public function store(Request $request)
    {
        $schoolId = $request->user()->school_id ?? \App\Models\School::first()?->id;

        // Convert empty string times to null
        $startTime = $request->filled('start_time') ? $request->start_time : null;
        $endTime = $request->filled('end_time') ? $request->end_time : null;

        $request->merge([
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'order_number' => 'nullable|integer',
            'active' => 'boolean',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i'
        ]);

        $orderNumber = $request->order_number;
        if (!$orderNumber) {
            $orderNumber = (Habit::where('school_id', $schoolId)->max('order_number') ?? 0) + 1;
        }

        $habit = Habit::create([
            'school_id' => $schoolId,
            'name' => $request->name,
            'order_number' => $orderNumber,
            'active' => $request->input('active', true),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'created_by' => $request->user()->id ?? null,
        ]);

        return $this->successResponse($habit, 'Kebiasaan berhasil ditambahkan', 201);
    }

    public function update(Request $request, $id)
    {
        $schoolId = $request->user()->school_id ?? \App\Models\School::first()?->id;
        $habit = Habit::where('school_id', $schoolId)->findOrFail($id);

        // Convert empty string times to null
        $startTime = $request->has('start_time') ? ($request->filled('start_time') ? $request->start_time : null) : $habit->start_time;
        $endTime = $request->has('end_time') ? ($request->filled('end_time') ? $request->end_time : null) : $habit->end_time;

        $request->merge([
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'order_number' => 'nullable|integer',
            'active' => 'boolean',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i'
        ]);

        $habit->update([
            'name' => $request->name,
            'order_number' => $request->order_number ?? $habit->order_number,
            'active' => $request->has('active') ? $request->active : $habit->active,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        return $this->successResponse($habit, 'Kebiasaan berhasil diperbarui');
    }

    public function destroy(Request $request, $id)
    {
        $habit = Habit::where('school_id', $request->user()->school_id)->findOrFail($id);
        $habit->delete();

        return $this->successResponse(null, 'Kebiasaan berhasil dihapus');
    }
}
