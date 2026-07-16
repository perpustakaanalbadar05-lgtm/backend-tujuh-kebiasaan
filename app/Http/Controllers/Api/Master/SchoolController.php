<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class SchoolController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = School::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('npsn', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $schools = $query->orderBy('name')->paginate(15);
        return $this->successResponse($schools, 'Data sekolah berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'npsn' => 'nullable|string|unique:schools,npsn',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $school = School::create([
            'name' => $request->name,
            'npsn' => $request->npsn,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        return $this->successResponse($school, 'Sekolah berhasil ditambahkan', 201);
    }

    public function show(Request $request, $id)
    {
        $school = School::find($id);

        if (!$school) {
            return $this->errorResponse('Sekolah tidak ditemukan', 404);
        }

        return $this->successResponse($school, 'Detail sekolah berhasil diambil');
    }

    public function update(Request $request, $id)
    {
        $school = School::find($id);

        if (!$school) {
            return $this->errorResponse('Sekolah tidak ditemukan', 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'npsn' => 'nullable|string|unique:schools,npsn,' . $id,
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $school->update([
            'name' => $request->name,
            'npsn' => $request->npsn,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'updated_by' => $request->user()->id,
        ]);

        return $this->successResponse($school, 'Sekolah berhasil diperbarui');
    }

    public function destroy(Request $request, $id)
    {
        $school = School::find($id);

        if (!$school) {
            return $this->errorResponse('Sekolah tidak ditemukan', 404);
        }

        $school->delete();
        return $this->successResponse(null, 'Sekolah berhasil dihapus');
    }

    public function toggleStatus(Request $request, $id)
    {
        $school = School::find($id);

        if (!$school) {
            return $this->errorResponse('Sekolah tidak ditemukan', 404);
        }

        $school->update([
            'status' => $school->status === 'active' ? 'inactive' : 'active',
            'updated_by' => $request->user()->id,
        ]);

        return $this->successResponse($school, 'Status sekolah berhasil diubah');
    }
}
