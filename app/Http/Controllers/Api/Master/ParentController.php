<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ParentsExport;
use App\Exports\ParentTemplateExport;

class ParentController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;

        $query = StudentParent::with('user', 'students')->where('school_id', $schoolId);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $parents = $query->orderBy('name')->paginate($request->query('per_page', 15));
        return $this->successResponse($parents, 'Data orang tua berhasil diambil');
    }

    public function store(Request $request)
    {
        $schoolId = $request->user()->school_id ?? \App\Models\School::first()?->id;

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string|min:6',
        ]);

        DB::beginTransaction();
        try {
            $baseUsername = $request->username ?: ($request->phone ?: ('ortu_' . Str::slug($request->name, '_')));
            if (empty($baseUsername)) $baseUsername = 'orangtua';
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
                if (empty($slug)) $slug = 'ortu';
                $userEmail = $slug . rand(100, 999) . '@parent.g7kaih.id';
                $ec = 1;
                while (User::where('email', $userEmail)->exists()) {
                    $userEmail = $slug . rand(100, 999) . '_' . $ec++ . '@parent.g7kaih.id';
                }
            }

            $password = $request->password ?: ($request->phone ?: 'password');

            $user = User::create([
                'name' => $request->name,
                'username' => $username,
                'email' => $userEmail,
                'password' => Hash::make($password),
                'role' => 'orangtua',
                'school_id' => $schoolId,
            ]);

            $parent = StudentParent::create([
                'school_id' => $schoolId,
                'user_id' => $user->id,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'created_by' => $request->user()->id ?? null,
            ]);

            if ($request->filled('student_id')) {
                DB::table('student_parents')->updateOrInsert(
                    ['parent_id' => $parent->id, 'student_id' => $request->student_id],
                    ['relationship' => $request->relationship ?? 'Wali', 'created_at' => now(), 'updated_at' => now()]
                );
            }

            DB::commit();
            return $this->successResponse($parent->load(['user', 'students']), 'Orang tua berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal menambahkan orang tua: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, $id)
    {
        $parent = StudentParent::with('user', 'students')
            ->where('school_id', $request->user()->school_id)
            ->find($id);

        if (!$parent) {
            return $this->errorResponse('Data orang tua tidak ditemukan', 404);
        }

        return $this->successResponse($parent, 'Detail orang tua berhasil diambil');
    }

    public function update(Request $request, $id)
    {
        $schoolId = $request->user()->school_id;
        $parent = StudentParent::where('school_id', $schoolId)->find($id);

        if (!$parent) {
            return $this->errorResponse('Data orang tua tidak ditemukan', 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $parent->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'updated_by' => $request->user()->id,
            ]);

            if ($parent->user_id) {
                User::where('id', $parent->user_id)->update(['name' => $request->name]);
            }

            DB::commit();
            return $this->successResponse($parent->load('user'), 'Orang tua berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal memperbarui orang tua: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $parent = StudentParent::where('school_id', $request->user()->school_id)->find($id);

        if (!$parent) {
            return $this->errorResponse('Data orang tua tidak ditemukan', 404);
        }

        $parent->delete();
        return $this->successResponse(null, 'Data orang tua berhasil dihapus');
    }

    public function resetPassword(Request $request, $id)
    {
        $user = $request->user();
        $query = StudentParent::query();
        if ($user->role !== 'superadmin') {
            $query->where('school_id', $user->school_id);
        }
        $parent = $query->find($id);

        if (!$parent) {
            return $this->errorResponse('Data orang tua tidak ditemukan', 404);
        }

        if ($parent->user_id) {
            $targetUser = User::find($parent->user_id);
            if ($targetUser) {
                $newPassword = $parent->phone ?? 'password';
                $targetUser->password = Hash::make($newPassword);
                $targetUser->save();
                return $this->successResponse(null, 'Password orang tua berhasil direset menjadi: ' . $newPassword);
            }
        }

        return $this->errorResponse('Akun pengguna orang tua tidak ditemukan', 404);
    }

    public function exportTemplate()
    {
        return Excel::download(new ParentTemplateExport, 'template_orangtua.xlsx');
    }

    public function export()
    {
        return Excel::download(new ParentsExport, 'data_orangtua.xlsx');
    }
}
