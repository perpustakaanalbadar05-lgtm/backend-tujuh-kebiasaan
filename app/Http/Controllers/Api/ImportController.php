<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\StudentsImport;
use App\Imports\TeachersImport;
use App\Imports\ParentsImport;
use App\Traits\ApiResponse;

class ImportController extends Controller
{
    use ApiResponse;

    public function importStudents(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls|max:5120',
        ]);

        $user = $request->user();
        if (!$user->school_id) {
            return $this->errorResponse('Anda tidak memiliki sekolah', 400);
        }

        try {
            Excel::import(new StudentsImport($user->school_id), $request->file('file'));
            return $this->successResponse(null, 'Data siswa berhasil diimport');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal melakukan import: ' . $e->getMessage(), 500);
        }
    }

    public function importTeachers(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls|max:5120',
        ]);

        $user = $request->user();
        if (!$user->school_id) {
            return $this->errorResponse('Anda tidak memiliki sekolah', 400);
        }

        try {
            Excel::import(new TeachersImport($user->school_id), $request->file('file'));
            return $this->successResponse(null, 'Data guru berhasil diimport');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal melakukan import: ' . $e->getMessage(), 500);
        }
    }

    public function importParents(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048'
        ]);

        try {
            Excel::import(new ParentsImport, $request->file('file'));
            return $this->successResponse(null, 'Data orang tua berhasil diimport', 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            return $this->errorResponse('Validasi gagal', 422, $failures);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal import data: ' . $e->getMessage(), 500);
        }
    }
}
