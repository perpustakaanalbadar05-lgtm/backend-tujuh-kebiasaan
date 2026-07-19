<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class SettingController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $settings = Setting::where('school_id', $request->user()->school_id)->get();
        return $this->successResponse($settings, 'Data pengaturan berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required'
        ]);

        $schoolId = $request->user()->school_id;
        
        foreach ($request->settings as $settingData) {
            Setting::updateOrCreate(
                ['school_id' => $schoolId, 'key' => $settingData['key']],
                ['value' => json_encode($settingData['value'])]
            );
        }

        return $this->successResponse(null, 'Pengaturan berhasil disimpan');
    }
}
