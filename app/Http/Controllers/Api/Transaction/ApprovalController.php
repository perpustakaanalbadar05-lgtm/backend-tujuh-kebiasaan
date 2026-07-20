<?php

namespace App\Http\Controllers\Api\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\Teacher;
use App\Models\StudentParent;
use App\Models\TeacherApproval;
use App\Models\ParentApproval;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class ApprovalController extends Controller
{
    use ApiResponse;

    public function approveByTeacher(Request $request, $journalId)
    {
        $user = $request->user();
        if ($user->role !== 'guru' && $user->role !== 'admin' && $user->role !== 'superadmin') {
            return $this->errorResponse('Anda tidak memiliki akses untuk validasi guru', 403);
        }

        $journal = Journal::find($journalId);
        if (!$journal) return $this->errorResponse('Jurnal tidak ditemukan', 404);

        $request->validate([
            'status' => 'required|in:approved,rejected',
            'note' => 'nullable|string',
            'overrides' => 'nullable|array',
            'overrides.*.id' => 'required|exists:journal_details,id',
            'overrides.*.is_done' => 'required|boolean',
        ]);

        $teacher = Teacher::where('user_id', $user->id)->first();
        
        // If it's an admin validating, teacher will be null, and teacher_id will be null.
        $approval = TeacherApproval::updateOrCreate(
            ['journal_id' => $journal->id],
            [
                'teacher_id' => $teacher ? $teacher->id : null,
                'status' => $request->status,
                'approved_at' => now(),
            ]
        );

        if ($request->status === 'approved' && $request->has('overrides')) {
            $totalPoints = 0;
            $maxPoints = \App\Models\Habit::where('school_id', $journal->school_id)->count() * 100;
            if ($maxPoints == 0) $maxPoints = 700; // Default fallback

            foreach ($request->overrides as $override) {
                $detail = \App\Models\JournalDetail::find($override['id']);
                if ($detail) {
                    $detail->update(['is_done' => $override['is_done']]);
                    if ($override['is_done']) {
                        $totalPoints += 100;
                    }
                }
            }
            
            // Recalculate score
            $score = round(($totalPoints / $maxPoints) * 100);
            $journal->update(['score' => $score]);
        }

        if ($journal->student && $journal->student->user_id) {
            \App\Models\Notification::create([
                'user_id' => $journal->student->user_id,
                'title' => 'Jurnal Divalidasi Guru',
                'message' => 'Jurnal Anda tanggal ' . $journal->date . ' telah di' . ($request->status == 'approved' ? 'setujui' : 'tolak') . ' oleh Guru.',
                'url' => '/dashboard/journal',
            ]);
        }

        return $this->successResponse($approval, 'Persetujuan guru berhasil disimpan');
    }

    public function approveByParent(Request $request, $journalId)
    {
        $user = $request->user();
        if ($user->role !== 'orangtua') {
            return $this->errorResponse('Anda tidak memiliki akses untuk validasi orangtua', 403);
        }

        $journal = Journal::find($journalId);
        if (!$journal) return $this->errorResponse('Jurnal tidak ditemukan', 404);

        $request->validate([
            'status' => 'required|in:approved,rejected',
            'note' => 'nullable|string',
            'overrides' => 'nullable|array',
            'overrides.*.id' => 'required|exists:journal_details,id',
            'overrides.*.is_done' => 'required|boolean',
        ]);

        $parent = StudentParent::where('user_id', $user->id)->first();
        
        $approval = ParentApproval::updateOrCreate(
            ['journal_id' => $journal->id],
            [
                'parent_id' => $parent ? $parent->id : null,
                'status' => $request->status,
                'note' => $request->note,
                'approved_at' => now(),
            ]
        );

        if ($request->status === 'approved' && $request->has('overrides')) {
            foreach ($request->overrides as $override) {
                \App\Models\JournalDetail::where('id', $override['id'])
                    ->where('journal_id', $journal->id)
                    ->update(['is_done' => $override['is_done']]);
            }
            
            // Recalculate score
            $totalHabits = $journal->details()->count();
            $doneHabits = $journal->details()->where('is_done', true)->count();
            $journal->score = $totalHabits > 0 ? round(($doneHabits / $totalHabits) * 100) : 0;
            $journal->save();
        }

        if ($journal->student && $journal->student->user_id) {
            \App\Models\Notification::create([
                'user_id' => $journal->student->user_id,
                'title' => 'Jurnal Divalidasi Orangtua',
                'message' => 'Jurnal Anda tanggal ' . $journal->date . ' telah di' . ($request->status == 'approved' ? 'setujui' : 'tolak') . ' oleh Orangtua.',
                'url' => '/dashboard/journal',
            ]);
        }

        return $this->successResponse($approval, 'Persetujuan orang tua berhasil disimpan');
    }
}
