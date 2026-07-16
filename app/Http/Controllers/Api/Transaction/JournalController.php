<?php

namespace App\Http\Controllers\Api\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Student;
use App\Models\StudentParent;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JournalController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Journal::with(['details', 'teacherApproval', 'parentApproval', 'student']);

        // Multi-role logic
        if ($user->role === 'siswa') {
            $student = Student::where('user_id', $user->id)->first();
            if (!$student) return $this->errorResponse('Data siswa tidak ditemukan', 404);
            $query->where('student_id', $student->id);
        } elseif ($user->role === 'orangtua') {
            $parent = StudentParent::where('user_id', $user->id)->first();
            if (!$parent) return $this->errorResponse('Data orangtua tidak ditemukan', 404);
            
            // Get student IDs associated with this parent
            $studentIds = DB::table('student_parents')->where('parent_id', $parent->id)->pluck('student_id');
            $query->whereIn('student_id', $studentIds);
        } elseif ($user->role === 'guru') {
            // Asumsi guru bisa melihat semua jurnal di sekolahnya, atau hanya kelas yang ia ampu
            // Untuk kesederhanaan saat ini, guru melihat jurnal di sekolahnya
            $query->whereHas('student', function($q) use ($user) {
                $q->where('school_id', $user->school_id);
            });
        } elseif ($user->role === 'admin' || $user->role === 'superadmin') {
            // Admin melihat semua jurnal di sekolahnya
            $query->whereHas('student', function($q) use ($user) {
                $q->where('school_id', $user->school_id);
            });
        }

        $journals = $query->orderBy('date', 'desc')->paginate(15);
        return $this->successResponse($journals, 'Data jurnal berhasil diambil');
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'siswa') {
            return $this->errorResponse('Hanya siswa yang dapat mengisi jurnal', 403);
        }

        $student = Student::where('user_id', $user->id)->first();
        if (!$student) return $this->errorResponse('Data siswa tidak ditemukan', 404);

        $request->validate([
            'date' => 'required|date',
            'habits' => 'required|array',
            'habits.*.habit_id' => 'required|exists:habits,id',
            'habits.*.is_done' => 'required|boolean',
            'habits.*.note' => 'nullable|string'
        ]);

        $date = Carbon::parse($request->date)->format('Y-m-d');

        // Validasi: 1 Hari Maksimal 1 Jurnal per Siswa
        $existingJournal = Journal::where('student_id', $student->id)->where('date', $date)->first();
        if ($existingJournal) {
            return $this->errorResponse("Anda sudah mengisi jurnal untuk tanggal {$date}", 422);
        }

        DB::beginTransaction();
        try {
            // Hitung total skor (asumsi is_done = 1 -> poin 100/jumlah_habits, atau simple boolean score)
            // Untuk simplicity, kita hitung persentase dari yang dikerjakan
            $totalHabits = count($request->habits);
            $doneHabits = collect($request->habits)->where('is_done', true)->count();
            $score = $totalHabits > 0 ? round(($doneHabits / $totalHabits) * 100) : 0;

            $journal = Journal::create([
                'student_id' => $student->id,
                'academic_year_id' => $student->schoolClass ? $student->schoolClass->academic_year_id : null, // Idealnya ambil dari relasi
                'semester_id' => null, // Opsional
                'date' => $date,
                'score' => $score,
                'status' => 'submitted',
                'created_by' => $user->id,
            ]);

            $details = [];
            foreach ($request->habits as $habit) {
                $details[] = [
                    'journal_id' => $journal->id,
                    'habit_id' => $habit['habit_id'],
                    'is_done' => $habit['is_done'],
                    'note' => $habit['note'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            JournalDetail::insert($details);

            DB::commit();

            // Create notification for class teacher
            if ($student && $student->schoolClass && $student->schoolClass->teacher_id) {
                $teacher = \App\Models\Teacher::find($student->schoolClass->teacher_id);
                if ($teacher && $teacher->user_id) {
                    \App\Models\Notification::create([
                        'user_id' => $teacher->user_id,
                        'title' => 'Jurnal Baru: ' . $student->name,
                        'message' => 'Siswa ' . $student->name . ' telah mengirimkan jurnal untuk tanggal ' . $date . '. Mohon segera divalidasi.',
                        'url' => '/dashboard/approvals',
                    ]);
                }
            }

            return $this->successResponse($journal->load('details'), 'Jurnal berhasil disubmit', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal menyimpan jurnal: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, $id)
    {
        $journal = Journal::with(['details', 'teacherApproval', 'parentApproval', 'student'])->find($id);
        
        if (!$journal) {
            return $this->errorResponse('Data jurnal tidak ditemukan', 404);
        }

        return $this->successResponse($journal, 'Detail jurnal berhasil diambil');
    }
}
