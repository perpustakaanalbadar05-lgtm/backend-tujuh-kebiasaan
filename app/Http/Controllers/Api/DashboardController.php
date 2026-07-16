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

        // MODE YAYASAN (SUPERADMIN)
        if ($user->role === 'superadmin') {
            $totalSchools = \App\Models\School::where('status', 'active')->count();
            $totalStudents = Student::where('status', 'active')->count();
            $totalTeachers = Teacher::count();

            $startOfWeek = Carbon::now()->startOfWeek();
            $endOfWeek = Carbon::now()->endOfWeek();

            // Leaderboard Sekolah (Berdasarkan jumlah jurnal minggu ini)
            $leaderboard = \App\Models\School::where('status', 'active')
                ->withCount(['students as active_journals' => function ($query) use ($startOfWeek, $endOfWeek) {
                    $query->join('journals', 'students.id', '=', 'journals.student_id')
                          ->whereBetween('journals.date', [$startOfWeek, $endOfWeek]);
                }])
                ->orderByDesc('active_journals')
                ->take(5)
                ->get()
                ->map(function ($school) {
                    return [
                        'name' => $school->name,
                        'journals' => $school->active_journals
                    ];
                });

            return $this->successResponse([
                'type' => 'yayasan',
                'total_schools' => $totalSchools,
                'total_students' => $totalStudents,
                'total_teachers' => $totalTeachers,
                'leaderboard' => $leaderboard
            ], 'Statistik yayasan berhasil diambil');
        }

        // MODE SEKOLAH (ADMIN, GURU, SISWA, ORTU)
        $schoolId = $user->school_id;

        $totalStudents = Student::where('school_id', $schoolId)->where('status', 'active')->count();
        $totalTeachers = Teacher::where('school_id', $schoolId)->count();

        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        
        $journalsThisWeek = Journal::whereHas('student', function($q) use ($schoolId) {
            $q->where('school_id', $schoolId);
        })
        ->whereBetween('date', [$startOfWeek, $endOfWeek])
        ->count();

        $dayOfWeek = Carbon::now()->dayOfWeekIso; 
        $maxPossibleJournals = $totalStudents * $dayOfWeek;
        $activityRate = $maxPossibleJournals > 0 
            ? round(($journalsThisWeek / $maxPossibleJournals) * 100) 
            : 0;

        if ($activityRate > 100) $activityRate = 100;

        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = Journal::whereHas('student', function($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })
            ->whereDate('date', $date->format('Y-m-d'))
            ->count();

            $hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            
            $chartData[] = [
                'name' => $hariIndo[$date->dayOfWeek],
                'partisipasi' => $count
            ];
        }

        return $this->successResponse([
            'type' => 'school',
            'total_students' => $totalStudents,
            'total_teachers' => $totalTeachers,
            'journals_this_week' => $journalsThisWeek,
            'activity_rate' => $activityRate,
            'chart_data' => $chartData
        ], 'Statistik sekolah berhasil diambil');
    }
}
