<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Journal;
use App\Models\StudentParent;
use App\Models\Predicate;
use App\Models\StudentBadge;
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
                          ->whereBetween('journals.journal_date', [$startOfWeek, $endOfWeek]);
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

            // National chart data (last 7 days)
            $chartData = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $count = Journal::whereDate('journal_date', $date->format('Y-m-d'))->count();

                $hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                
                $chartData[] = [
                    'name' => $hariIndo[$date->dayOfWeek],
                    'partisipasi' => $count
                ];
            }

            return $this->successResponse([
                'type' => 'yayasan',
                'total_schools' => $totalSchools,
                'total_students' => $totalStudents,
                'total_teachers' => $totalTeachers,
                'leaderboard' => $leaderboard,
                'chart_data' => $chartData
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
        ->whereBetween('journal_date', [$startOfWeek, $endOfWeek])
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
            ->whereDate('journal_date', $date->format('Y-m-d'))
            ->count();

            $hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            
            $chartData[] = [
                'name' => $hariIndo[$date->dayOfWeek],
                'partisipasi' => $count
            ];
        }

        if ($user->role === 'siswa' || $user->role === 'orangtua') {
            $studentId = null;
            if ($user->role === 'siswa') {
                $student = Student::where('user_id', $user->id)->first();
                $studentId = $student ? $student->id : null;
            } else {
                $parent = StudentParent::where('user_id', $user->id)->first();
                if ($parent) {
                    $student = $parent->students()->first();
                    $studentId = $student ? $student->id : null;
                }
            }

            if ($studentId) {
                // Personal Stats
                $totalScore = Journal::where('student_id', $studentId)->whereNotNull('score')->sum('score');
                
                $journalsThisMonth = Journal::where('student_id', $studentId)
                    ->whereMonth('journal_date', Carbon::now()->month)
                    ->whereYear('journal_date', Carbon::now()->year)
                    ->count();

                $filledToday = Journal::where('student_id', $studentId)
                    ->whereDate('journal_date', Carbon::now()->format('Y-m-d'))
                    ->exists();

                $predicate = Predicate::where('school_id', $schoolId)
                    ->where('min_score', '<=', $totalScore)
                    ->where('max_score', '>=', $totalScore)
                    ->first();
                $currentPredicate = $predicate ? $predicate->name : 'Belum Ada Predikat';

                // Recent Badges
                $recentBadges = StudentBadge::with('badge')
                    ->where('student_id', $studentId)
                    ->orderByDesc('awarded_at')
                    ->take(3)
                    ->get()
                    ->map(function ($sb) {
                        return [
                            'name' => $sb->badge->name,
                            'icon' => $sb->badge->icon,
                            'awarded_at' => Carbon::parse($sb->awarded_at)->diffForHumans()
                        ];
                    });

                // Personal chart data (last 7 days - scores)
                $studentChartData = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = Carbon::now()->subDays($i);
                    $journal = Journal::where('student_id', $studentId)
                        ->whereDate('journal_date', $date->format('Y-m-d'))
                        ->first();

                    $hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                    
                    $studentChartData[] = [
                        'name' => $hariIndo[$date->dayOfWeek],
                        'score' => $journal ? ($journal->score ?? 0) : 0
                    ];
                }

                return $this->successResponse([
                    'type' => 'student',
                    'student_name' => $student->name,
                    'total_score' => $totalScore,
                    'journals_this_month' => $journalsThisMonth,
                    'current_predicate' => $currentPredicate,
                    'filled_today' => $filledToday,
                    'recent_badges' => $recentBadges,
                    'chart_data' => $studentChartData
                ], 'Statistik personal berhasil diambil');
            }
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
