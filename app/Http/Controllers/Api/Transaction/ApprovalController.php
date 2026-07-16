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
            'status' => 'required|in:approved,rejected'
        ]);

        $teacher = Teacher::where('user_id', $user->id)->first();
        
        $approval = TeacherApproval::updateOrCreate(
            ['journal_id' => $journal->id],
            [
                'teacher_id' => $teacher ? $teacher->id : null,
                'status' => $request->status,
                'approved_at' => now(),
            ]
        );

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
            'note' => 'nullable|string'
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
