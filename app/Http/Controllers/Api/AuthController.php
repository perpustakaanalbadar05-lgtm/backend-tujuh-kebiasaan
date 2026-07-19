<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use App\Traits\ApiResponse;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Login User and create token
     */
    public function login(LoginRequest $request)
    {

        $credentials = [
            'username' => $request->username,
            'password' => $request->password
        ];

        // Fallback to email if it looks like an email
        if (filter_var($request->username, FILTER_VALIDATE_EMAIL)) {
            $credentials = [
                'email' => $request->username,
                'password' => $request->password
            ];
        }

        if (!Auth::attempt($credentials)) {
            return $this->errorResponse('Kredensial tidak valid', 401);
        }

        $user = $request->user();
        
        // Buat token dengan membawa role sebagai identifier (abilities)
        $token = $user->createToken('auth_token')->plainTextToken;

        // Log Activity
        \Illuminate\Support\Facades\DB::table('activity_logs')->insert([
            'user_id' => $user->id,
            'school_id' => $user->school_id,
            'action' => 'Login',
            'description' => 'User berhasil login ke dalam sistem.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user)
        ], 'Login berhasil');
    }

    /**
     * Get User Profile
     */
    public function me(Request $request)
    {
        // Load relasi sekolah jika ada
        $user = $request->user()->load('school');
        return $this->successResponse(new UserResource($user), 'Profil pengguna berhasil dimuat');
    }

    /**
     * Logout User (Revoke Token)
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        // Log Activity
        \Illuminate\Support\Facades\DB::table('activity_logs')->insert([
            'user_id' => $user->id,
            'school_id' => $user->school_id,
            'action' => 'Logout',
            'description' => 'User keluar dari sistem.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Berhasil logout');
    }
}
