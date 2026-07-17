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
        $request->validate([
            'name' => 'required|string|max:255',
            'order_number' => 'required|integer',
            'active' => 'boolean'
        ]);

        $habit = Habit::create([
            'school_id' => $request->user()->school_id,
            'name' => $request->name,
            'order_number' => $request->order_number,
            'active' => $request->input('active', true),
        ]);

        return $this->successResponse($habit, 'Kebiasaan berhasil ditambahkan', 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'order_number' => 'required|integer',
            'active' => 'boolean'
        ]);

        $habit = Habit::where('school_id', $request->user()->school_id)->findOrFail($id);
        $habit->update($request->only(['name', 'order_number', 'active']));

        return $this->successResponse($habit, 'Kebiasaan berhasil diperbarui');
    }

    public function destroy(Request $request, $id)
    {
        $habit = Habit::where('school_id', $request->user()->school_id)->findOrFail($id);
        $habit->delete();

        return $this->successResponse(null, 'Kebiasaan berhasil dihapus');
    }
}
