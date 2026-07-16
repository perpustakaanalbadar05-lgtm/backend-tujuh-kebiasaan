<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class TeacherController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;
        
        $query = Teacher::where('school_id', $schoolId);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%");
        }

        $teachers = $query->paginate(15);
        return $this->successResponse($teachers, 'Data guru berhasil diambil');
    }

    public function store(Request $request)
    {
        // Logika simpan (Store) dapat ditambahkan di sini nantinya (Mirip Student)
        return $this->errorResponse('Not Implemented Yet', 501);
    }

    public function show(Request $request, $id)
    {
        $teacher = Teacher::where('school_id', $request->user()->school_id)->find($id);
        
        if (!$teacher) {
            return $this->errorResponse('Data guru tidak ditemukan', 404);
        }

        return $this->successResponse($teacher, 'Detail guru berhasil diambil');
    }

    public function update(Request $request, $id)
    {
        return $this->errorResponse('Not Implemented Yet', 501);
    }

    public function destroy(Request $request, $id)
    {
        $teacher = Teacher::where('school_id', $request->user()->school_id)->find($id);

        if (!$teacher) {
            return $this->errorResponse('Data guru tidak ditemukan', 404);
        }

        $teacher->delete();
        return $this->successResponse(null, 'Data guru berhasil dihapus');
    }
}
