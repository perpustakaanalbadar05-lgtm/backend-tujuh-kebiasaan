<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Journal;
use App\Models\Habit;
use App\Models\StudentParent;
use App\Traits\ApiResponse;
use Carbon\Carbon;

class ReportController extends Controller
{
    use ApiResponse;

    public function getStudentReport(Request $request, $studentId)
    {
        $user = $request->user();
        
        // Proteksi: Jika Siswa/Ortu, pastikan hanya bisa lihat laporannya sendiri
        if ($user->role === 'siswa') {
            $student = Student::where('user_id', $user->id)->first();
            if (!$student || $student->id != $studentId) {
                return $this->errorResponse('Unauthorized access', 403);
            }
        }
        
        // Proteksi Orang Tua: Hanya bisa lihat laporannya anaknya sendiri
        if ($user->role === 'orangtua') {
            $parent = StudentParent::where('user_id', $user->id)->first();
            if (!$parent) {
                return $this->errorResponse('Data orang tua tidak ditemukan', 404);
            }
            $isParent = $parent->students()->where('student_id', $studentId)->exists();
            if (!$isParent) {
                return $this->errorResponse('Unauthorized access', 403);
            }
        }

        $student = Student::with('school')->findOrFail($studentId);
        
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));
        
        // Ambil semua jurnal siswa di bulan & tahun yang diminta
        $journals = Journal::with('details')
            ->where('student_id', $studentId)
            ->whereMonth('journal_date', $month)
            ->whereYear('journal_date', $year)
            ->get();

        $totalDays = $journals->count();
        $averageScore = $totalDays > 0 ? round($journals->avg('score'), 2) : 0;
        
        // Penentuan Predikat
        $predicate = 'D'; // Kurang (<50%)
        if ($averageScore >= 85) {
            $predicate = 'A'; // Sangat Baik
        } elseif ($averageScore >= 70) {
            $predicate = 'B'; // Baik
        } elseif ($averageScore >= 50) {
            $predicate = 'C'; // Cukup
        }

        // Rekapitulasi per-habit
        $activeHabits = Habit::where('school_id', $student->school_id)
                             ->where('active', true)
                             ->orderBy('order_number')
                             ->get();
                             
        $habitBreakdown = [];
        foreach ($activeHabits as $habit) {
            $doneCount = 0;
            foreach ($journals as $journal) {
                $detail = $journal->details->where('habit_id', $habit->id)->first();
                if ($detail && $detail->is_done) {
                    $doneCount++;
                }
            }
            
            $habitBreakdown[] = [
                'habit_id' => $habit->id,
                'habit_name' => $habit->name,
                'done_count' => $doneCount,
                'percentage' => $totalDays > 0 ? round(($doneCount / $totalDays) * 100) : 0
            ];
        }

        return $this->successResponse([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'school' => $student->school->name ?? 'Sekolah'
            ],
            'period' => [
                'month' => $month,
                'year' => $year
            ],
            'summary' => [
                'total_days_filled' => $totalDays,
                'average_score' => (int)$averageScore,
                'predicate' => $predicate
            ],
            'habit_breakdown' => $habitBreakdown
        ], 'Laporan bulanan berhasil di-generate');
    }

    public function exportExcel(Request $request)
    {
        $schoolId = $request->user()->school_id;
        $academicYearId = $request->query('academic_year_id');
        $semesterId = $request->query('semester_id');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ReportExport($schoolId, $academicYearId, $semesterId), 
            'laporan_rekap_siswa.xlsx'
        );
    }
}
