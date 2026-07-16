<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class AnnouncementController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Announcement::query();

        if ($user->school_id) {
            $query->where(function($q) use ($user) {
                $q->where('school_id', $user->school_id)
                  ->orWhereNull('school_id'); // Global announcements
            });
        }

        $query->where('is_active', true);

        if ($request->has('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        $announcements = $query->orderByDesc('created_at')->paginate(15);
        return $this->successResponse($announcements, 'Data pengumuman berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $announcement = Announcement::create([
            'school_id' => $request->user()->school_id,
            'title' => $request->title,
            'content' => $request->content,
            'is_active' => true,
        ]);

        return $this->successResponse($announcement, 'Pengumuman berhasil dibuat', 201);
    }

    public function show($id)
    {
        $announcement = Announcement::find($id);

        if (!$announcement) {
            return $this->errorResponse('Pengumuman tidak ditemukan', 404);
        }

        return $this->successResponse($announcement, 'Detail pengumuman berhasil diambil');
    }

    public function update(Request $request, $id)
    {
        $announcement = Announcement::find($id);

        if (!$announcement) {
            return $this->errorResponse('Pengumuman tidak ditemukan', 404);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $announcement->update([
            'title' => $request->title,
            'content' => $request->content,
            'is_active' => $request->is_active ?? $announcement->is_active,
        ]);

        return $this->successResponse($announcement, 'Pengumuman berhasil diperbarui');
    }

    public function destroy($id)
    {
        $announcement = Announcement::find($id);

        if (!$announcement) {
            return $this->errorResponse('Pengumuman tidak ditemukan', 404);
        }

        $announcement->delete();
        return $this->successResponse(null, 'Pengumuman berhasil dihapus');
    }
}
