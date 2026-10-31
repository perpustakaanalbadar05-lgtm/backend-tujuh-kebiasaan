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
        $user = $request->user();
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
            if ($school) {
                if ($user->exists) {
                    $user->school_id = $school->id;
                    $user->save();
                }
            } else {
                $school = School::create([
                    'name' => 'Nama Sekolah',
                    'status' => 'active',
                ]);
                if ($user->exists) {
                    $user->school_id = $school->id;
                    $user->save();
                }
                School::initializeDefaults($school->id, $user->id);
            }
        }

        // Ambil settings untuk sekolah ini
        $settings = Setting::where('school_id', $school->id)->get()->pluck('value', 'key');

        return $this->successResponse([
            'school' => $school,
            'settings' => $settings
        ], 'Profil sekolah berhasil diambil');
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin' && $user->role !== 'superadmin') {
            return $this->errorResponse('Akses ditolak', 403);
        }

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
            'active_modules' => 'nullable|string', // JSON string
        ]);

        $schoolId = $user->school_id;
        $school = null;

        if ($schoolId) {
            $school = School::withTrashed()->find($schoolId);
            if ($school && $school->trashed()) {
                $school->restore();
            }
        }

        if (!$school) {
            // Cari berdasarkan nama atau sekolah pertama yang ada
            $school = School::where('name', $request->name)->first();
            if (!$school) {
                $school = School::first();
            }

            if (!$school) {
                $school = School::create([
                    'name' => $request->name,
                    'npsn' => $request->npsn,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'address' => $request->address,
                    'theme_color' => $request->theme_color ?? '#4CAF50',
                    'status' => 'active',
                ]);
                School::initializeDefaults($school->id, $user->id);
            }

            if ($user->exists) {
                $user->school_id = $school->id;
                $user->save();
            }
        }

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
            'active_modules' => $request->active_modules,
        ];

        foreach ($settingsToUpdate as $key => $value) {
            if ($value !== null) {
                Setting::updateOrCreate(
                    ['school_id' => $school->id, 'key' => $key],
                    ['value' => $value]
                );
            }
        }

        return $this->successResponse($school, 'Profil sekolah berhasil diperbarui');
    }
}
