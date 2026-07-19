<?php

namespace App\Http\Controllers\Api\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\Student;
use App\Models\StudentBadge;
use App\Models\Journal;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class AchievementController extends Controller
{
    use ApiResponse;

    // Get all achievements for a specific student
    public function getStudentAchievements(Request $request, $studentId = null)
    {
        $user = $request->user();
        
        if ($user->role === 'siswa') {
            $student = Student::where('user_id', $user->id)->first();
            if (!$student) return $this->errorResponse('Data siswa tidak ditemukan', 404);
            $studentId = $student->id;
        } else {
            if (!$studentId) return $this->errorResponse('ID Siswa diperlukan', 400);
            $student = Student::find($studentId);
            if (!$student) return $this->errorResponse('Data siswa tidak ditemukan', 404);
            // TODO: Add role checking for parents, teachers, etc.
        }

        // Get all badges available for this school (global + school specific)
        $availableBadges = Badge::where(function($q) use ($student) {
            $q->whereNull('school_id')->orWhere('school_id', $student->school_id);
        })->where('is_active', true)->get();

        // Get earned badges
        $earnedBadges = StudentBadge::with(['badge', 'awardedBy'])
                                    ->where('student_id', $studentId)
                                    ->get();

        return $this->successResponse([
            'available_badges' => $availableBadges,
            'earned_badges' => $earnedBadges
        ], 'Data pencapaian berhasil diambil');
    }

    // Award a badge manually (Teacher/Admin)
    public function awardBadge(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'guru' && $user->role !== 'admin' && $user->role !== 'superadmin') {
            return $this->errorResponse('Unauthorized access', 403);
        }

        $request->validate([
            'student_id' => 'required|exists:students,id',
            'badge_id' => 'required|exists:badges,id'
        ]);

        $studentBadge = StudentBadge::firstOrCreate([
            'student_id' => $request->student_id,
            'badge_id' => $request->badge_id
        ], [
            'awarded_at' => now(),
            'awarded_by' => $user->id
        ]);

        return $this->successResponse($studentBadge, 'Badge berhasil diberikan');
    }

    // Auto-evaluate badges based on journals
    public static function checkAutoBadges($studentId)
    {
        $student = Student::find($studentId);
        if (!$student) return;

        // Get available auto badges
        $availableBadges = Badge::where(function($q) use ($student) {
            $q->whereNull('school_id')->orWhere('school_id', $student->school_id);
        })->where('is_active', true)
          ->where('condition_type', 'consistent_days')
          ->get();

        if ($availableBadges->isEmpty()) return;

        // Fetch student's recent journals ordered by date descending
        $journals = Journal::where('student_id', $studentId)
            ->orderBy('journal_date', 'desc')
            ->get();

        if ($journals->isEmpty()) return;

        // Calculate current streak
        $streak = 0;
        $currentDate = \Carbon\Carbon::now()->format('Y-m-d');
        $checkDate = \Carbon\Carbon::parse($journals->first()->journal_date);
        
        // If the most recent journal is not today or yesterday, streak is broken
        if ($checkDate->diffInDays(\Carbon\Carbon::now()) > 1) {
             // Streak is effectively 0 for today
        } else {
             $streak = 1;
             for ($i = 1; $i < count($journals); $i++) {
                 $prevDate = \Carbon\Carbon::parse($journals[$i]->journal_date);
                 if ($checkDate->diffInDays($prevDate) == 1) {
                     $streak++;
                     $checkDate = $prevDate;
                 } else {
                     break;
                 }
             }
        }

        // Award badges based on streak
        foreach ($availableBadges as $badge) {
            if ($streak >= $badge->condition_value) {
                // Check if they already have it
                $hasBadge = StudentBadge::where('student_id', $studentId)->where('badge_id', $badge->id)->exists();
                if (!$hasBadge) {
                    StudentBadge::create([
                        'student_id' => $studentId,
                        'badge_id' => $badge->id,
                        'awarded_at' => now(),
                        'awarded_by' => null // Auto system
                    ]);
                }
            }
        }
    }
}
