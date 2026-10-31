<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use App\Exports\TeachersExport;
use App\Exports\TeacherTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeacherController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;
        
        $query = Teacher::with('user')->where('school_id', $schoolId);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $teachers = $query->orderBy('name')->paginate($request->query('per_page', 15));
        return $this->successResponse($teachers, 'Data guru berhasil diambil');
    }

    public function store(Request $request)
    {
        $schoolId = $request->user()->school_id ?? \App\Models\School::first()?->id;

        $request->validate([
            'name' => 'required|string|max:255',
            'nip' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'password' => 'nullable|string|min:6',
        ]);

        DB::beginTransaction();
        try {
            $baseUsername = $request->nip ?: Str::slug($request->name, '.');
            if (empty($baseUsername)) {
                $baseUsername = 'guru';
            }
            $username = $baseUsername;
            $counter = 1;
            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . '_' . $counter++;
            }

            $userEmail = $request->email;
            if ($userEmail && User::where('email', $userEmail)->exists()) {
                $userEmail = null;
            }
            if (!$userEmail) {
                $slug = Str::slug($request->name, '');
                if (empty($slug)) $slug = 'guru';
                $userEmail = $slug . rand(100, 999) . '@teacher.g7kaih.id';
                $ec = 1;
                while (User::where('email', $userEmail)->exists()) {
                    $userEmail = $slug . rand(100, 999) . '_' . $ec++ . '@teacher.g7kaih.id';
                }
            }

            $password = $request->password ?: ($request->nip ?: 'password');

            $user = User::create([
                'name' => $request->name,
                'username' => $username,
                'email' => $userEmail,
                'password' => Hash::make($password),
                'role' => 'guru',
                'school_id' => $schoolId,
            ]);

            $teacher = Teacher::create([
                'school_id' => $schoolId,
                'user_id' => $user->id,
                'nip' => $request->nip,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'created_by' => $request->user()->id ?? null,
            ]);

            DB::commit();
            return $this->successResponse($teacher->load('user'), 'Data guru berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal menambahkan data guru: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, $id)
    {
        $teacher = Teacher::with('user')->where('school_id', $request->user()->school_id)->find($id);
        
        if (!$teacher) {
            return $this->errorResponse('Data guru tidak ditemukan', 404);
        }

        return $this->successResponse($teacher, 'Detail guru berhasil diambil');
    }

    public function update(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;
        $teacher = Teacher::where('school_id', $schoolId)->find($id);

        if (!$teacher) {
            return $this->errorResponse('Data guru tidak ditemukan', 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'nip' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $teacher->update([
                'nip' => $request->nip,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'updated_by' => $request->user()->id,
            ]);

            if ($teacher->user_id) {
                User::where('id', $teacher->user_id)->update(['name' => $request->name]);
            }

            DB::commit();
            return $this->successResponse($teacher->load('user'), 'Data guru berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal memperbarui data guru: ' . $e->getMessage(), 500);
        }
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

    public function resetPassword(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;
        $teacher = Teacher::where('school_id', $schoolId)->find($id);

        if (!$teacher) {
            return $this->errorResponse('Data guru tidak ditemukan', 404);
        }

        if ($teacher->user_id) {
            $user = User::find($teacher->user_id);
            if ($user) {
                // If NIP is null, use a generic fallback or return error
                $password = $teacher->nip ?? 'password';
                $user->password = Hash::make($password);
                $user->save();
            }
        }

        return $this->successResponse(null, 'Password berhasil direset');
    }

    public function export(Request $request)
    {
        $schoolId = $request->user()->school_id;
        return Excel::download(new TeachersExport($schoolId), 'data_guru.xlsx');
    }

    public function exportTemplate()
    {
        return Excel::download(new TeacherTemplateExport, 'template_data_guru.xlsx');
    }
}
