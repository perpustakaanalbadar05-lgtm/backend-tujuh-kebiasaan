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
            'feedback' => 'nullable|string'
        ]);

        $teacher = Teacher::where('user_id', $user->id)->first();
        
        $approval = TeacherApproval::updateOrCreate(
            ['journal_id' => $journal->id],
            [
                'teacher_id' => $teacher ? $teacher->id : null,
                'status' => $request->status,
                'feedback' => $request->feedback,
                'approved_at' => now(),
                'created_by' => $user->id,
            ]
        );

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
            'feedback' => 'nullable|string'
        ]);

        $parent = StudentParent::where('user_id', $user->id)->first();
        
        $approval = ParentApproval::updateOrCreate(
            ['journal_id' => $journal->id],
            [
                'parent_id' => $parent ? $parent->id : null,
                'status' => $request->status,
                'feedback' => $request->feedback,
                'approved_at' => now(),
                'created_by' => $user->id,
            ]
        );

        return $this->successResponse($approval, 'Persetujuan orang tua berhasil disimpan');
    }
}
