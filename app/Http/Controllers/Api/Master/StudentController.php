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
use Illuminate\Support\Str;

class StudentController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        $schoolId = $user->school_id;
        
        // Eager load relasi schoolClass
        $query = Student::with('schoolClass');
        if ($user->role === 'superadmin') {
            if ($request->has('school_id')) {
                $query->where('school_id', $request->school_id);
            }
        } else {
            $query->where('school_id', $schoolId);
        }

        // Filter Wali Kelas: Guru hanya melihat siswa dari kelas yang dia ampu
        if ($user->role === 'guru') {
            $teacher = \App\Models\Teacher::where('user_id', $user->id)->first();
            if ($teacher) {
                $classIds = \Illuminate\Support\Facades\DB::table('class_teacher')->where('teacher_id', $teacher->id)->pluck('class_id')->toArray();
                $classIds2 = \App\Models\SchoolClass::where('teacher_id', $teacher->id)->pluck('id')->toArray();
                $allClassIds = array_unique(array_merge($classIds, $classIds2));
                
                $query->where(function($q) use ($allClassIds, $teacher) {
                    $q->where('validator_id', $teacher->id)
                      ->orWhere(function($subQ) use ($allClassIds) {
                          $subQ->whereNull('validator_id')->whereIn('class_id', $allClassIds);
                      });
                });
            } else {
                $query->where('id', 0);
            }
        }

        // Pencarian opsional
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }
        // Ambil semua data (tanpa paginasi) dan urutkan dari yang terbaru
        // Ini diperlukan karena frontend menggunakan pencarian client-side
        $students = $query->orderBy('created_at', 'desc')->get();
        return $this->successResponse($students, 'Data siswa berhasil diambil');
    }

    public function store(Request $request)
    {
        $schoolId = $request->user()->school_id ?? $request->school_id ?? \App\Models\School::first()?->id;

        $request->validate([
            'name' => 'required|string|max:255',
            'nis' => 'nullable|string',
            'class_id' => 'nullable',
            'gender' => 'nullable|string',
        ]);

        // Auto-resolve class_id
        $classId = $request->class_id;
        if (!$classId || !\App\Models\SchoolClass::where('school_id', $schoolId)->where('id', $classId)->exists()) {
            $existingClass = \App\Models\SchoolClass::where('school_id', $schoolId)->first();
            if (!$existingClass) {
                \App\Models\School::initializeDefaults($schoolId, $request->user()->id ?? null);
                $ay = \App\Models\AcademicYear::where('school_id', $schoolId)->where('active', true)->first();
                $existingClass = \App\Models\SchoolClass::create([
                    'school_id' => $schoolId,
                    'academic_year_id' => $ay ? $ay->id : 1,
                    'name' => 'Kelas 7A',
                    'grade' => '7',
                    'active' => true,
                ]);
            }
            $classId = $existingClass->id;
        }

        // Normalize gender
        $rawGender = strtoupper(substr(trim($request->gender ?? 'L'), 0, 1));
        $gender = ($rawGender === 'P') ? 'P' : 'L';

        // Normalize NIS
        $nis = trim((string)($request->nis ?? ''));
        if (empty($nis)) {
            $nis = date('Y') . str_pad((string)(Student::where('school_id', $schoolId)->count() + 1), 4, '0', STR_PAD_LEFT);
        }

        if (Student::where('school_id', $schoolId)->where('nis', $nis)->exists()) {
            return $this->errorResponse('Siswa dengan NIS ' . $nis . ' sudah terdaftar di sekolah ini.', 422);
        }

        DB::beginTransaction();
        try {
            // Unique username
            $baseUsername = $nis;
            $username = $baseUsername;
            $counter = 1;
            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . '_' . $counter++;
            }

            // Unique email
            $slug = Str::slug($request->name, '');
            if (empty($slug)) $slug = 'siswa';
            $userEmail = $slug . $nis . '@student.g7kaih.id';
            $ec = 1;
            while (User::where('email', $userEmail)->exists()) {
                $userEmail = $slug . $nis . '_' . $ec++ . '@student.g7kaih.id';
            }

            $password = $request->password ?: ($nis ?: 'password');

            // Buat akun user siswa
            $user = User::create([
                'name' => $request->name,
                'username' => $username,
                'email' => $userEmail,
                'password' => Hash::make($password),
                'role' => 'siswa',
                'school_id' => $schoolId,
            ]);

            // Buat data master siswa
            $student = Student::create([
                'school_id' => $schoolId,
                'user_id' => $user->id,
                'class_id' => $classId,
                'nis' => $nis,
                'name' => $request->name,
                'gender' => $gender,
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
        $user = $request->user();
        $query = Student::with('schoolClass');
        if ($user->role !== 'superadmin') {
            $query->where('school_id', $user->school_id);
        }
        $student = $query->find($id);
        
        if (!$student) {
            return $this->errorResponse('Data siswa tidak ditemukan', 404);
        }

        return $this->successResponse($student, 'Detail siswa berhasil diambil');
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $schoolId = $user->school_id ?? $request->school_id;
        $student = $user->role === 'superadmin' 
            ? Student::find($id) 
            : Student::where('school_id', $schoolId)->find($id);

        if (!$student) {
            return $this->errorResponse('Data siswa tidak ditemukan', 404);
        }

        $request->validate([
            'nis' => 'required|string|unique:students,nis,' . $id . ',id,school_id,' . $student->school_id . '|unique:users,username,' . $student->user_id,
            'name' => 'required|string',
            'class_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('classes', 'id')->where('school_id', $student->school_id),
            ],
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
        $user = $request->user();
        $query = Student::query();
        if ($user->role !== 'superadmin') {
            $query->where('school_id', $user->school_id);
        }
        $student = $query->find($id);

        if (!$student) {
            return $this->errorResponse('Data siswa tidak ditemukan', 404);
        }

        $student->delete();
        return $this->successResponse(null, 'Data siswa berhasil dihapus');
    }

    public function resetPassword(Request $request, $id)
    {
        $user = $request->user();
        $query = Student::query();
        if ($user->role !== 'superadmin') {
            $query->where('school_id', $user->school_id);
        }
        $student = $query->find($id);

        if (!$student) {
            return $this->errorResponse('Data siswa tidak ditemukan', 404);
        }

        if ($student->user_id) {
            $targetUser = User::find($student->user_id);
            if ($targetUser) {
                $targetUser->password = Hash::make($student->nis);
                $targetUser->save();
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
