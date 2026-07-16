<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Journal;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    public function stats(Request $request)
    {
        $user = $request->user();
        $schoolId = $user->school_id;

        // 1. Total Students
        $totalStudents = Student::where('school_id', $schoolId)->where('status', 'active')->count();

        // 2. Total Teachers
        $totalTeachers = Teacher::where('school_id', $schoolId)->count();

        // 3. Jurnal Minggu Ini
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        
        $journalsThisWeek = Journal::whereHas('student', function($q) use ($schoolId) {
            $q->where('school_id', $schoolId);
        })
        ->whereBetween('date', [$startOfWeek, $endOfWeek])
        ->count();

        // 4. Activity Rate (Tingkat Aktivitas)
        // Rumus: (Jurnal minggu ini) / (Total Siswa x hari-berjalan dalam minggu ini)
        $dayOfWeek = Carbon::now()->dayOfWeekIso; // 1 (Senin) - 7 (Minggu)
        $maxPossibleJournals = $totalStudents * $dayOfWeek;
        $activityRate = $maxPossibleJournals > 0 
            ? round(($journalsThisWeek / $maxPossibleJournals) * 100) 
            : 0;

        // Bounding max 100%
        if ($activityRate > 100) $activityRate = 100;

        // 5. Trend Chart (7 Hari Terakhir)
        // Kita butuh struktur: { name: 'Senin', partisipasi: 12 }
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = Journal::whereHas('student', function($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })
            ->whereDate('date', $date->format('Y-m-d'))
            ->count();

            // Menerjemahkan hari ke bahasa Indonesia
            $hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            
            $chartData[] = [
                'name' => $hariIndo[$date->dayOfWeek],
                'partisipasi' => $count
            ];
        }

        return $this->successResponse([
            'total_students' => $totalStudents,
            'total_teachers' => $totalTeachers,
            'journals_this_week' => $journalsThisWeek,
            'activity_rate' => $activityRate,
            'chart_data' => $chartData
        ], 'Statistik dashboard berhasil diambil');
    }
}
