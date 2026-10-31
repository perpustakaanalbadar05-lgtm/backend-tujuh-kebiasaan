<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Traits\ApiResponse;

class SchoolController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = School::with('admin');

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
            'admin_name' => 'nullable|string|max:255',
            'admin_username' => 'nullable|string|max:255|unique:users,username',
            'admin_email' => 'nullable|email|unique:users,email',
            'admin_password' => 'nullable|string|min:6',
        ]);

        return DB::transaction(function () use ($request) {
            $school = School::create([
                'name' => $request->name,
                'npsn' => $request->npsn,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'status' => 'active',
                'created_by' => $request->user()->id,
            ]);

            // Buat akun Admin Sekolah otomatis
            $baseUsername = $request->admin_username ?: ($request->npsn ? 'admin_' . $request->npsn : 'admin_' . Str::slug($request->name, '_'));
            $adminUsername = $baseUsername;
            $counter = 1;
            while (User::where('username', $adminUsername)->exists()) {
                $adminUsername = $baseUsername . '_' . $counter++;
            }

            $adminEmail = $request->admin_email ?: $request->email;
            if ($adminEmail && User::where('email', $adminEmail)->exists()) {
                $adminEmail = null;
            }

            $adminName = $request->admin_name ?: 'Admin ' . $school->name;
            $adminPassword = $request->admin_password ?: 'password';

            $admin = User::create([
                'name' => $adminName,
                'username' => $adminUsername,
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
                'school_id' => $school->id,
            ]);

            $school->setRelation('admin', $admin);

            return $this->successResponse([
                'school' => $school,
                'admin' => $admin,
                'admin_default_password' => $adminPassword,
            ], 'Sekolah dan akun Admin Sekolah berhasil ditambahkan', 201);
        });
    }

    public function show(Request $request, $id)
    {
        $school = School::with('admin')->find($id);

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

    public function createOrResetAdmin(Request $request, $id)
    {
        $school = School::find($id);
        if (!$school) {
            return $this->errorResponse('Sekolah tidak ditemukan', 404);
        }

        $request->validate([
            'name' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'password' => 'nullable|string|min:6',
        ]);

        $admin = $school->admin;

        $username = $request->username ?: ($admin ? $admin->username : ($school->npsn ? 'admin_' . $school->npsn : 'admin_' . Str::slug($school->name, '_')));
        $email = $request->email ?: ($admin ? $admin->email : $school->email);
        $password = $request->password ?: 'password';
        $name = $request->name ?: ($admin ? $admin->name : 'Admin ' . $school->name);

        if ($admin) {
            if (User::where('username', $username)->where('id', '!=', $admin->id)->exists()) {
                return $this->errorResponse('Username sudah digunakan oleh akun lain', 422);
            }
            if ($email && User::where('email', $email)->where('id', '!=', $admin->id)->exists()) {
                return $this->errorResponse('Email sudah digunakan oleh akun lain', 422);
            }

            $admin->update([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($password),
            ]);
            $message = 'Akun Admin Sekolah berhasil diperbarui';
        } else {
            $baseUsername = $username;
            $counter = 1;
            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . '_' . $counter++;
            }

            if ($email && User::where('email', $email)->exists()) {
                $email = null;
            }

            $admin = User::create([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'admin',
                'school_id' => $school->id,
            ]);
            $message = 'Akun Admin Sekolah berhasil dibuat';
        }

        return $this->successResponse([
            'admin' => $admin,
            'default_password' => $password,
        ], $message);
    }
}
