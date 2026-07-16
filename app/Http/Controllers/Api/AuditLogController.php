<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\ApiResponse;

class AuditLogController extends Controller
{
    use ApiResponse;

    public function activityLogs(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin' && $user->role !== 'superadmin') {
            return $this->errorResponse('Akses ditolak', 403);
        }

        $query = DB::table('activity_logs')
            ->join('users', 'activity_logs.user_id', '=', 'users.id')
            ->select('activity_logs.*', 'users.name as user_name', 'users.role as user_role');

        if ($user->role === 'admin') {
            $query->where('activity_logs.school_id', $user->school_id);
        }

        $logs = $query->orderBy('activity_logs.created_at', 'desc')->paginate(20);

        return $this->successResponse($logs, 'Activity logs berhasil diambil');
    }

    public function auditLogs(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin' && $user->role !== 'superadmin') {
            return $this->errorResponse('Akses ditolak', 403);
        }

        $query = DB::table('audit_logs')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->select('audit_logs.*', 'users.name as user_name', 'users.role as user_role');

        $logs = $query->orderBy('audit_logs.created_at', 'desc')->paginate(20);

        return $this->successResponse($logs, 'Audit logs berhasil diambil');
    }
}
