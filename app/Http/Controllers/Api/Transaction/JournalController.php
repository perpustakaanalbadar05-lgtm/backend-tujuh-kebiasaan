<?php

namespace App\Http\Controllers\Api\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\Setting;
use App\Models\Semester;
use App\Models\Holiday;
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
        $query = Journal::with(['details.habit', 'teacherApproval', 'parentApproval', 'student']);

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
            // Filter Wali Kelas: Guru hanya melihat jurnal dari kelas yang dia ampu
            $teacher = \App\Models\Teacher::where('user_id', $user->id)->first();
            if ($teacher) {
                $classIds = \Illuminate\Support\Facades\DB::table('class_teacher')->where('teacher_id', $teacher->id)->pluck('class_id')->toArray();
                $classIds2 = \App\Models\SchoolClass::where('teacher_id', $teacher->id)->pluck('id')->toArray();
                $allClassIds = array_unique(array_merge($classIds, $classIds2));
                
                $query->whereHas('student', function($q) use ($allClassIds, $teacher) {
                    $q->where('validator_id', $teacher->id)
                      ->orWhere(function($subQ) use ($allClassIds) {
                          $subQ->whereNull('validator_id')->whereIn('class_id', $allClassIds);
                      });
                });
            } else {
                $query->where('id', 0);
            }
        } elseif ($user->role === 'admin' || $user->role === 'superadmin') {
            // Admin melihat semua jurnal di sekolahnya
            $query->whereHas('student', function($q) use ($user) {
                $q->where('school_id', $user->school_id);
            });
        }

        $journals = $query->orderBy('journal_date', 'desc')->paginate(15);
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
            'habits.*.time_performed' => 'nullable|date_format:H:i',
            'habits.*.note' => 'nullable|string'
        ]);

        $date = Carbon::parse($request->date)->format('Y-m-d');

        // Validasi: Cek status jurnal jika sudah ada
        $existingJournal = Journal::with(['teacherApproval', 'parentApproval'])
            ->where('student_id', $student->id)
            ->where('journal_date', $date)
            ->first();

        if ($existingJournal) {
            $isTeacherProcessed = $existingJournal->teacherApproval && in_array($existingJournal->teacherApproval->status, ['approved', 'rejected']);
            $isParentProcessed = $existingJournal->parentApproval && in_array($existingJournal->parentApproval->status, ['approved', 'rejected']);
            
            if ($isTeacherProcessed || $isParentProcessed) {
                return $this->errorResponse("Jurnal untuk tanggal {$date} sudah divalidasi dan dikunci. Anda tidak dapat mengubahnya lagi.", 422);
            }
        }

        // Validasi Jam Operasional
        $settings = Setting::where('school_id', $student->school_id)->pluck('value', 'key');
        $startTime = $settings['journal_start_time'] ?? '00:00';
        $endTime = $settings['journal_end_time'] ?? '23:59';
        $now = now()->format('H:i');

        if ($now < $startTime || $now > $endTime) {
            return $this->errorResponse("Sistem pengisian jurnal hanya dibuka dari jam {$startTime} sampai {$endTime}", 422);
        }

        // Validasi Hari Libur
        $isHoliday = Holiday::where('school_id', $student->school_id)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
            
        if ($isHoliday) {
            return $this->errorResponse("Tanggal {$date} adalah hari libur, Anda tidak perlu mengisi jurnal", 422);
        }

        // Cari Semester dan Tahun Ajaran Aktif
        $activeSemester = Semester::where('school_id', $student->school_id)->where('active', true)->first();
        if (!$activeSemester) {
            return $this->errorResponse('Tidak ada semester aktif di sekolah Anda. Harap hubungi Admin.', 400);
        }

        DB::beginTransaction();
        try {
            // Hitung total skor (asumsi is_done = 1 -> poin 100/jumlah_habits, atau simple boolean score)
            // Untuk simplicity, kita hitung persentase dari yang dikerjakan
            $totalHabits = count($request->habits);
            $doneHabits = collect($request->habits)->where('is_done', true)->count();
            $score = $totalHabits > 0 ? round(($doneHabits / $totalHabits) * 100) : 0;

            if ($existingJournal) {
                $existingJournal->update([
                    'score' => $score,
                    'status' => 'submitted'
                ]);
                $journal = $existingJournal;
                
                foreach ($request->habits as $habit) {
                    JournalDetail::updateOrCreate(
                        ['journal_id' => $journal->id, 'habit_id' => $habit['habit_id']],
                        [
                            'is_done' => $habit['is_done'],
                            'time_performed' => $habit['time_performed'] ?? null,
                            'note' => $habit['note'] ?? null,
                        ]
                    );
                }
            } else {
                $journal = Journal::create([
                    'school_id' => $student->school_id,
                    'student_id' => $student->id,
                    'academic_year_id' => $activeSemester->academic_year_id,
                    'semester_id' => $activeSemester->id,
                    'journal_date' => $date,
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
                        'time_performed' => $habit['time_performed'] ?? null,
                        'note' => $habit['note'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                JournalDetail::insert($details);

                // Create notification for class teacher ONLY on first creation
                if ($student && $student->schoolClass && $student->schoolClass->teacher_id) {
                    $teacher = \App\Models\Teacher::find($student->schoolClass->teacher_id);
                    if ($teacher && $teacher->user_id) {
                        \App\Models\Notification::create([
                            'user_id' => $teacher->user_id,
                            'title' => 'Jurnal Baru: ' . $student->name,
                            'message' => 'Siswa ' . $student->name . ' telah mulai mengisi jurnal untuk tanggal ' . $date . '.',
                            'url' => '/dashboard/approvals',
                        ]);
                    }
                }
            }

            DB::commit();

            // Auto-evaluate badges for streak
            \App\Http\Controllers\Api\Transaction\AchievementController::checkAutoBadges($student->id);

            return $this->successResponse($journal->load('details'), 'Jurnal berhasil disubmit', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal menyimpan jurnal: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, $id)
    {
        $journal = Journal::with(['details.habit', 'teacherApproval', 'parentApproval', 'student'])->find($id);
        
        if (!$journal) {
            return $this->errorResponse('Data jurnal tidak ditemukan', 404);
        }

        return $this->successResponse($journal, 'Detail jurnal berhasil diambil');
    }

    public function today(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'siswa') {
            return $this->errorResponse('Hanya siswa yang dapat mengakses', 403);
        }

        $student = Student::where('user_id', $user->id)->first();
        if (!$student) return $this->errorResponse('Data siswa tidak ditemukan', 404);

        $date = now()->format('Y-m-d');
        
        $journal = Journal::with(['details.habit', 'teacherApproval', 'parentApproval'])
            ->where('student_id', $student->id)
            ->where('journal_date', $date)
            ->first();

        // Check if journal exists and is locked
        $isLocked = false;
        if ($journal) {
            $isTeacherProcessed = $journal->teacherApproval && in_array($journal->teacherApproval->status, ['approved', 'rejected']);
            $isParentProcessed = $journal->parentApproval && in_array($journal->parentApproval->status, ['approved', 'rejected']);
            $isLocked = $isTeacherProcessed || $isParentProcessed;
        }

        return $this->successResponse([
            'journal' => $journal,
            'is_locked' => $isLocked
        ], 'Jurnal hari ini');
    }
}
