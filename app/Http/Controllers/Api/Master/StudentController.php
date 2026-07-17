<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use App\Exports\StudentsExport;
use App\Exports\StudentTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;
        
        // Eager load relasi schoolClass
        $query = Student::with('schoolClass')->where('school_id', $schoolId);

        // Pencarian opsional
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        $students = $query->paginate(15);
        return $this->successResponse($students, 'Data siswa berhasil diambil');
    }

    public function store(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $request->validate([
            'nis' => 'required|string|unique:students,nis,NULL,id,school_id,' . $schoolId,
            'name' => 'required|string',
            'class_id' => 'required|exists:classes,id',
            'gender' => 'required|in:L,P',
        ]);

        DB::beginTransaction();
        try {
            // Buat akun user siswa terlebih dahulu
            $user = User::create([
                'name' => $request->name,
                'username' => $request->nis, // Default username = NIS
                'email' => strtolower(str_replace(' ', '', $request->name)) . rand(10,99) . '@student.g7kaih.id',
                'password' => Hash::make('password'),
                'role' => 'siswa',
                'school_id' => $schoolId,
            ]);

            // Buat data master siswa
            $student = Student::create([
                'school_id' => $schoolId,
                'user_id' => $user->id,
                'class_id' => $request->class_id,
                'nis' => $request->nis,
                'name' => $request->name,
                'gender' => $request->gender,
                'status' => 'active',
            ]);

            DB::commit();
            return $this->successResponse($student->load('schoolClass'), 'Data siswa berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal menambahkan data siswa: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, $id)
    {
        $student = Student::with('schoolClass')->where('school_id', $request->user()->school_id)->find($id);
        
        if (!$student) {
            return $this->errorResponse('Data siswa tidak ditemukan', 404);
        }

        return $this->successResponse($student, 'Detail siswa berhasil diambil');
    }

    public function update(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;
        $student = Student::where('school_id', $schoolId)->find($id);

        if (!$student) {
            return $this->errorResponse('Data siswa tidak ditemukan', 404);
        }

        $request->validate([
            'nis' => 'required|string|unique:students,nis,' . $id . ',id,school_id,' . $schoolId,
            'name' => 'required|string',
            'class_id' => 'required|exists:classes,id',
            'gender' => 'required|in:L,P',
            'status' => 'required|string'
        ]);

        DB::beginTransaction();
        try {
            $student->update([
                'nis' => $request->nis,
                'name' => $request->name,
                'class_id' => $request->class_id,
                'gender' => $request->gender,
                'status' => $request->status,
            ]);

            // Sinkronkan nama di tabel users
            if ($student->user_id) {
                User::where('id', $student->user_id)->update(['name' => $request->name]);
            }

            DB::commit();
            return $this->successResponse($student->load('schoolClass'), 'Data siswa berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal memperbarui data siswa: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $student = Student::where('school_id', $request->user()->school_id)->find($id);

        if (!$student) {
            return $this->errorResponse('Data siswa tidak ditemukan', 404);
        }

        $student->delete();
        return $this->successResponse(null, 'Data siswa berhasil dihapus');
    }

    public function resetPassword(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;
        $student = Student::where('school_id', $schoolId)->find($id);

        if (!$student) {
            return $this->errorResponse('Data siswa tidak ditemukan', 404);
        }

        if ($student->user_id) {
            $user = User::find($student->user_id);
            if ($user) {
                $user->password = Hash::make($student->nis);
                $user->save();
            }
        }

        return $this->successResponse(null, 'Password berhasil direset menjadi NIS');
    }

    public function export(Request $request)
    {
        $schoolId = $request->user()->school_id;
        return Excel::download(new StudentsExport($schoolId), 'data_siswa.xlsx');
    }

    public function exportTemplate()
    {
        return Excel::download(new StudentTemplateExport, 'template_data_siswa.xlsx');
    }
}
