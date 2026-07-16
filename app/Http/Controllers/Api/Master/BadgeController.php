<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class BadgeController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Badge::query();
        
        if ($user->role === 'superadmin') {
            // Superadmin sees all
        } else {
            // Others see global badges (school_id null) + their school's badges
            $query->whereNull('school_id')->orWhere('school_id', $user->school_id);
        }

        $badges = $query->orderBy('school_id')->orderBy('id')->get();
        return $this->successResponse($badges, 'Data badge berhasil diambil');
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin' && $user->role !== 'superadmin') {
            return $this->errorResponse('Unauthorized access', 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'condition_type' => 'required|in:consistent_days,perfect_score,habit_specific,manual',
            'condition_value' => 'nullable|integer',
            'is_global' => 'boolean'
        ]);

        // Only superadmin can create global badges
        $schoolId = $request->is_global && $user->role === 'superadmin' ? null : $user->school_id;

        $badge = Badge::create([
            'school_id' => $schoolId,
            'name' => $request->name,
            'description' => $request->description,
            'icon' => $request->icon,
            'condition_type' => $request->condition_type,
            'condition_value' => $request->condition_value,
            'is_active' => true,
        ]);

        return $this->successResponse($badge, 'Badge berhasil ditambahkan', 201);
    }

    public function show($id)
    {
        $badge = Badge::findOrFail($id);
        return $this->successResponse($badge, 'Detail badge berhasil diambil');
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $badge = Badge::findOrFail($id);

        if ($user->role !== 'superadmin' && $badge->school_id !== $user->school_id) {
            return $this->errorResponse('Anda tidak memiliki akses untuk mengubah badge ini', 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'condition_type' => 'required|in:consistent_days,perfect_score,habit_specific,manual',
            'condition_value' => 'nullable|integer',
            'is_active' => 'boolean'
        ]);

        $badge->update($request->only([
            'name', 'description', 'icon', 'condition_type', 'condition_value', 'is_active'
        ]));

        return $this->successResponse($badge, 'Badge berhasil diperbarui');
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $badge = Badge::findOrFail($id);

        if ($user->role !== 'superadmin' && $badge->school_id !== $user->school_id) {
            return $this->errorResponse('Anda tidak memiliki akses untuk menghapus badge ini', 403);
        }

        $badge->delete();
        return $this->successResponse(null, 'Badge berhasil dihapus');
    }
}
