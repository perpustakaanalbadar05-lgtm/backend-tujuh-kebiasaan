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

    // Optional: Auto-evaluate badges based on journals (can be called via Job/Cron or after journal submit)
    public function checkAutoBadges($studentId)
    {
        // Implementation for condition_type like 'consistent_days', 'perfect_score'
        // For example, finding if a student has submitted journals 7 days in a row
    }
}
