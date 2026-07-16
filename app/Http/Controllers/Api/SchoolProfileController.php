<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\School;
use App\Models\Setting;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Storage;

class SchoolProfileController extends Controller
{
    use ApiResponse;

    public function show(Request $request)
    {
        $schoolId = $request->user()->school_id;
        if (!$schoolId) {
            return $this->errorResponse('Anda tidak memiliki sekolah', 400);
        }

        $school = School::find($schoolId);
        
        // Ambil settings untuk sekolah ini
        $settings = Setting::where('school_id', $schoolId)->get()->pluck('value', 'key');

        return $this->successResponse([
            'school' => $school,
            'settings' => $settings
        ], 'Profil sekolah berhasil diambil');
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $schoolId = $user->school_id;
        if (!$schoolId) {
            return $this->errorResponse('Anda tidak memiliki sekolah', 400);
        }

        if ($user->role !== 'admin' && $user->role !== 'superadmin') {
            return $this->errorResponse('Akses ditolak', 403);
        }

        $school = School::find($schoolId);

        $request->validate([
            'name' => 'required|string|max:255',
            'npsn' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'theme_color' => 'nullable|string|max:50',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            
            // Settings
            'principal_name' => 'nullable|string|max:255',
            'journal_start_time' => 'nullable|string',
            'journal_end_time' => 'nullable|string',
        ]);

        // Update School
        $schoolData = $request->only(['name', 'npsn', 'email', 'phone', 'address', 'theme_color']);
        
        if ($request->hasFile('logo')) {
            if ($school->logo) {
                Storage::disk('public')->delete($school->logo);
            }
            $path = $request->file('logo')->store('logos', 'public');
            $schoolData['logo'] = $path;
        }

        $school->update($schoolData);

        // Update Settings
        $settingsToUpdate = [
            'principal_name' => $request->principal_name,
            'journal_start_time' => $request->journal_start_time,
            'journal_end_time' => $request->journal_end_time,
        ];

        foreach ($settingsToUpdate as $key => $value) {
            if ($value !== null) {
                Setting::updateOrCreate(
                    ['school_id' => $schoolId, 'key' => $key],
                    ['value' => $value]
                );
            }
        }

        return $this->successResponse($school, 'Profil sekolah berhasil diperbarui');
    }
}
