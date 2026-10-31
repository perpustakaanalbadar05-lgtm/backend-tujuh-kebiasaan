<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\StudentsImport;
use App\Imports\TeachersImport;
use App\Imports\ParentsImport;
use App\Models\School;
use App\Traits\ApiResponse;

class ImportController extends Controller
{
    use ApiResponse;

    private function resolveSchoolId($user)
    {
        $schoolId = $user->school_id;
        $school = null;
        if ($schoolId) {
            $school = School::withTrashed()->find($schoolId);
            if ($school && $school->trashed()) {
                $school->restore();
            }
        }

        if (!$school) {
            $school = School::first();
            if ($school && $user->exists) {
                $user->school_id = $school->id;
                $user->save();
            }
        }

        return $school ? $school->id : null;
    }

    private function validateExcelFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            return false;
        }
        return true;
    }

    public function importStudents(Request $request)
    {
        if (!$this->validateExcelFile($request)) {
            return $this->errorResponse('Format file harus berupa Excel (.xlsx, .xls) atau CSV (.csv).', 422);
        }

        $schoolId = $this->resolveSchoolId($request->user());
        if (!$schoolId) {
            return $this->errorResponse('Data sekolah belum ditemukan. Silakan lengkapi Profil Sekolah terlebih dahulu.', 400);
        }

        try {
            Excel::import(new StudentsImport($schoolId), $request->file('file'));
            return $this->successResponse(null, 'Data siswa berhasil diimport');
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            return $this->errorResponse($this->formatValidationErrors($e), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal melakukan import: ' . $e->getMessage(), 500);
        }
    }

    public function importTeachers(Request $request)
    {
        if (!$this->validateExcelFile($request)) {
            return $this->errorResponse('Format file harus berupa Excel (.xlsx, .xls) atau CSV (.csv).', 422);
        }

        $schoolId = $this->resolveSchoolId($request->user());
        if (!$schoolId) {
            return $this->errorResponse('Data sekolah belum ditemukan. Silakan lengkapi Profil Sekolah terlebih dahulu.', 400);
        }

        try {
            Excel::import(new TeachersImport($schoolId), $request->file('file'));
            return $this->successResponse(null, 'Data guru berhasil diimport');
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            return $this->errorResponse($this->formatValidationErrors($e), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal melakukan import: ' . $e->getMessage(), 500);
        }
    }

    public function importParents(Request $request)
    {
        if (!$this->validateExcelFile($request)) {
            return $this->errorResponse('Format file harus berupa Excel (.xlsx, .xls) atau CSV (.csv).', 422);
        }

        $schoolId = $this->resolveSchoolId($request->user());
        if (!$schoolId) {
            return $this->errorResponse('Data sekolah belum ditemukan. Silakan lengkapi Profil Sekolah terlebih dahulu.', 400);
        }

        try {
            Excel::import(new ParentsImport($schoolId), $request->file('file'));
            return $this->successResponse(null, 'Data orang tua berhasil diimport', 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            return $this->errorResponse($this->formatValidationErrors($e), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal import data: ' . $e->getMessage(), 500);
        }
    }

    private function formatValidationErrors(\Maatwebsite\Excel\Validators\ValidationException $e)
    {
        $failures = $e->failures();
        $errorMessages = [];
        $count = 0;
        foreach ($failures as $failure) {
            $row = $failure->row();
            foreach ($failure->errors() as $err) {
                $errorMessages[] = "Baris ke-{$row}: {$err}";
                $count++;
                if ($count >= 5) break 2;
            }
        }
        
        if (count($failures) > 5) {
            $more = count($failures) - 5;
            $errorMessages[] = "... dan {$more} baris error lainnya. Mohon perbaiki dan coba lagi.";
        }
        
        return "Terdapat kesalahan pengisian Excel:\n" . implode("\n", $errorMessages);
    }
}
