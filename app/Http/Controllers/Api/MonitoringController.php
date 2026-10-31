<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    use ApiResponse;

    protected function getSchoolId(Request $request)
    {
        return $request->user()->school_id ?? $request->get('school_id');
    }

    public function daily(Request $request)
    {
        $schoolId = $this->getSchoolId($request);
        if (!$schoolId) {
            return $this->errorResponse('School ID diperlukan', 400);
        }

        $date = $request->get('date', now()->toDateString());

        $data = DB::table('journals')
            ->where('journals.school_id', $schoolId)
            ->where('journals.journal_date', $date)
            ->selectRaw("
                COUNT(*) as total_journals,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status IN ('submitted', 'pending_parent', 'pending_teacher') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'rejected' OR status = 'revision' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft
            ")
            ->first();

        $totalStudents = DB::table('students')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->count();

        return $this->successResponse([
            'date' => $date,
            'total_students' => $totalStudents,
            'filled' => $data->total_journals ?? 0,
            'not_filled' => max(0, $totalStudents - ($data->total_journals ?? 0)),
            'fill_rate' => $totalStudents > 0 ? round((($data->total_journals ?? 0) / $totalStudents) * 100, 1) : 0,
            'status_breakdown' => [
                'approved' => (int) ($data->approved ?? 0),
                'pending' => (int) ($data->pending ?? 0),
                'rejected' => (int) ($data->rejected ?? 0),
                'draft' => (int) ($data->draft ?? 0),
            ],
        ], 'Monitoring harian berhasil diambil');
    }

    public function weekly(Request $request)
    {
        $schoolId = $this->getSchoolId($request);
        if (!$schoolId) {
            return $this->errorResponse('School ID diperlukan', 400);
        }

        $endDate = $request->get('end_date', now()->toDateString());
        $startDate = $request->get('start_date', now()->subDays(6)->toDateString());

        $data = DB::table('journals')
            ->where('school_id', $schoolId)
            ->whereBetween('journal_date', [$startDate, $endDate])
            ->selectRaw("
                journal_date as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
            ")
            ->groupBy('journal_date')
            ->orderBy('journal_date')
            ->get();

        $totalStudents = DB::table('students')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->count();

        return $this->successResponse([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_students' => $totalStudents,
            'daily_data' => $data,
        ], 'Monitoring mingguan berhasil diambil');
    }

    public function monthly(Request $request)
    {
        $schoolId = $this->getSchoolId($request);
        if (!$schoolId) {
            return $this->errorResponse('School ID diperlukan', 400);
        }

        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $data = DB::table('journals')
            ->where('school_id', $schoolId)
            ->whereMonth('journal_date', $month)
            ->whereYear('journal_date', $year)
            ->selectRaw("
                journal_date as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
            ")
            ->groupBy('journal_date')
            ->orderBy('journal_date')
            ->get();

        // Per-class breakdown
        $classData = DB::table('journals')
            ->join('students', 'journals.student_id', '=', 'students.id')
            ->join('classes', 'students.class_id', '=', 'classes.id')
            ->where('journals.school_id', $schoolId)
            ->whereMonth('journals.journal_date', $month)
            ->whereYear('journals.journal_date', $year)
            ->selectRaw("
                classes.name as class_name,
                classes.id as class_id,
                COUNT(*) as total,
                SUM(CASE WHEN journals.status = 'approved' THEN 1 ELSE 0 END) as approved
            ")
            ->groupBy('classes.id', 'classes.name')
            ->orderBy('classes.name')
            ->get();

        return $this->successResponse([
            'month' => $month,
            'year' => $year,
            'daily_data' => $data,
            'class_data' => $classData,
        ], 'Monitoring bulanan berhasil diambil');
    }

    public function semester(Request $request)
    {
        $schoolId = $this->getSchoolId($request);
        if (!$schoolId) {
            return $this->errorResponse('School ID diperlukan', 400);
        }

        // Get active semester date range (simplified)
        $data = DB::table('journals')
            ->join('students', 'journals.student_id', '=', 'students.id')
            ->join('classes', 'students.class_id', '=', 'classes.id')
            ->where('journals.school_id', $schoolId)
            ->selectRaw("
                classes.name as class_name,
                classes.id as class_id,
                COUNT(*) as total_journals,
                SUM(CASE WHEN journals.status = 'approved' THEN 1 ELSE 0 END) as approved,
                COUNT(DISTINCT journals.student_id) as active_students
            ")
            ->groupBy('classes.id', 'classes.name')
            ->orderBy('classes.name')
            ->get();

        // Per-habit summary
        $habitData = DB::table('journal_details')
            ->join('journals', 'journal_details.journal_id', '=', 'journals.id')
            ->join('habits', 'journal_details.habit_id', '=', 'habits.id')
            ->where('journals.school_id', $schoolId)
            ->selectRaw("
                habits.name as habit_name,
                habits.id as habit_id,
                SUM(CASE WHEN journal_details.is_done = 1 THEN 1 ELSE 0 END) as done_count,
                COUNT(*) as total_count
            ")
            ->groupBy('habits.id', 'habits.name')
            ->orderBy('habits.order_number')
            ->get();

        return $this->successResponse([
            'class_data' => $data,
            'habit_data' => $habitData,
        ], 'Monitoring semester berhasil diambil');
    }

    public function classComparison(Request $request)
    {
        $schoolId = $this->getSchoolId($request);
        if (!$schoolId) {
            return $this->errorResponse('School ID diperlukan', 400);
        }
        
        $data = DB::table('classes')
            ->leftJoin('students', function($join) {
                $join->on('classes.id', '=', 'students.class_id')
                     ->where('students.status', '=', 'active');
            })
            ->leftJoin('journals', 'students.id', '=', 'journals.student_id')
            ->where('classes.school_id', $schoolId)
            ->where('classes.active', true)
            ->selectRaw("
                classes.id as class_id,
                classes.name as class_name,
                COUNT(DISTINCT students.id) as total_students,
                COUNT(DISTINCT journals.id) as total_journals_filled,
                AVG(journals.score) as average_score
            ")
            ->groupBy('classes.id', 'classes.name')
            ->orderBy('classes.name')
            ->get();

        // Calculate participation rate
        foreach ($data as $item) {
            $item->average_score = $item->average_score ? round($item->average_score, 1) : 0;
            $item->total_journals_filled = $item->total_journals_filled ?? 0;
        }

        return $this->successResponse($data, 'Data perbandingan kelas berhasil diambil');
    }
}

