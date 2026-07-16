<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class HabitController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $schoolId = $request->user()->school_id;
        
        $habits = Habit::where('school_id', $schoolId)
                       ->where('active', true)
                       ->orderBy('order_number')
                       ->get();

        return $this->successResponse($habits, 'Daftar kebiasaan berhasil diambil');
    }
}
