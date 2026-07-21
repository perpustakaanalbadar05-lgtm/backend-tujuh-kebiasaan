<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

// Public Routes
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Profile
    Route::get('/profile', [\App\Http\Controllers\Api\ProfileController::class, 'show']);
    Route::put('/profile', [\App\Http\Controllers\Api\ProfileController::class, 'update']);
    Route::put('/change-password', [\App\Http\Controllers\Api\ProfileController::class, 'changePassword']);

    // School Profile (Admin Sekolah)
    Route::middleware(['role:admin,superadmin'])->group(function() {
        Route::get('/school-profile', [\App\Http\Controllers\Api\SchoolProfileController::class, 'show']);
        Route::post('/school-profile', [\App\Http\Controllers\Api\SchoolProfileController::class, 'update']);
    });
    
    // Students list for dropdowns (accessible by teacher, admin)
    Route::get('/students', [\App\Http\Controllers\Api\Master\StudentController::class, 'index']);
    
    // Import Data
    Route::post('/import/students', [\App\Http\Controllers\Api\ImportController::class, 'importStudents']);
    Route::post('/import/teachers', [\App\Http\Controllers\Api\ImportController::class, 'importTeachers']);
    Route::post('/import/parents', [\App\Http\Controllers\Api\ImportController::class, 'importParents']);

    // Master Data (Public to authenticated users)
    Route::get('master/habits', [\App\Http\Controllers\Api\Master\HabitController::class, 'index']);

    // Master Data Routes (Admin Only)
    Route::prefix('master')->middleware(['role:admin,superadmin'])->group(function() {
        Route::apiResource('schools', \App\Http\Controllers\Api\Master\SchoolController::class);
        Route::patch('schools/{id}/status', [\App\Http\Controllers\Api\Master\SchoolController::class, 'toggleStatus']);

        Route::get('students/template', [\App\Http\Controllers\Api\Master\StudentController::class, 'exportTemplate']);
        Route::get('students/export', [\App\Http\Controllers\Api\Master\StudentController::class, 'export']);
        Route::patch('students/{id}/reset-password', [\App\Http\Controllers\Api\Master\StudentController::class, 'resetPassword']);
        Route::apiResource('students', \App\Http\Controllers\Api\Master\StudentController::class);

        Route::get('teachers/template', [\App\Http\Controllers\Api\Master\TeacherController::class, 'exportTemplate']);
        Route::get('teachers/export', [\App\Http\Controllers\Api\Master\TeacherController::class, 'export']);
        Route::patch('teachers/{id}/reset-password', [\App\Http\Controllers\Api\Master\TeacherController::class, 'resetPassword']);
        Route::apiResource('teachers', \App\Http\Controllers\Api\Master\TeacherController::class);
        
        // Exclude index since it's defined above for all users
        Route::apiResource('habits', \App\Http\Controllers\Api\Master\HabitController::class)->except('index');
        
        Route::apiResource('academic-years', \App\Http\Controllers\Api\Master\AcademicYearController::class);
        Route::apiResource('classes', \App\Http\Controllers\Api\Master\SchoolClassController::class);
        Route::apiResource('semesters', \App\Http\Controllers\Api\Master\SemesterController::class);
        Route::patch('semesters/{id}/active', [\App\Http\Controllers\Api\Master\SemesterController::class, 'activate']);

        Route::apiResource('holidays', \App\Http\Controllers\Api\Master\HolidayController::class);
        
        Route::get('parents/template', [\App\Http\Controllers\Api\Master\ParentController::class, 'exportTemplate']);
        Route::get('parents/export', [\App\Http\Controllers\Api\Master\ParentController::class, 'export']);
        Route::apiResource('parents', \App\Http\Controllers\Api\Master\ParentController::class);
        
        Route::apiResource('predicates', \App\Http\Controllers\Api\Master\PredicateController::class);

        // Mapping Routes
        Route::get('mappings/teacher-classes', [\App\Http\Controllers\Api\Master\MappingController::class, 'teacherClasses']);
        Route::post('mappings/teacher-classes', [\App\Http\Controllers\Api\Master\MappingController::class, 'assignTeacherClass']);
        Route::delete('mappings/teacher-classes/{id}', [\App\Http\Controllers\Api\Master\MappingController::class, 'removeTeacherClass']);

        Route::get('mappings/parent-students', [\App\Http\Controllers\Api\Master\MappingController::class, 'parentStudents']);
        Route::post('mappings/parent-students', [\App\Http\Controllers\Api\Master\MappingController::class, 'assignParentStudent']);
        Route::delete('mappings/parent-students/{id}', [\App\Http\Controllers\Api\Master\MappingController::class, 'removeParentStudent']);

        Route::get('mappings/teacher-students', [\App\Http\Controllers\Api\Master\MappingController::class, 'teacherStudents']);
        Route::post('mappings/teacher-students/bulk', [\App\Http\Controllers\Api\Master\MappingController::class, 'assignTeacherStudentsBulk']);
        Route::delete('mappings/teacher-students/{id}', [\App\Http\Controllers\Api\Master\MappingController::class, 'removeTeacherStudent']);

        // Gamification Master
        Route::apiResource('badges', \App\Http\Controllers\Api\Master\BadgeController::class);
    });

    // Transaction Routes
    Route::prefix('journals')->group(function() {
        Route::get('/', [\App\Http\Controllers\Api\Transaction\JournalController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\Transaction\JournalController::class, 'store']);
        Route::get('/today', [\App\Http\Controllers\Api\Transaction\JournalController::class, 'today']);
        Route::get('/{id}', [\App\Http\Controllers\Api\Transaction\JournalController::class, 'show']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\Transaction\JournalController::class, 'destroy']);        
        // Approvals
        Route::post('/{id}/approve-teacher', [\App\Http\Controllers\Api\Transaction\ApprovalController::class, 'approveByTeacher']);
        Route::post('/{id}/approve-parent', [\App\Http\Controllers\Api\Transaction\ApprovalController::class, 'approveByParent']);
    });

    // Gamification Transactions (Achievements)
    Route::get('/achievements/{studentId?}', [\App\Http\Controllers\Api\Transaction\AchievementController::class, 'getStudentAchievements']);
    Route::post('/achievements/award', [\App\Http\Controllers\Api\Transaction\AchievementController::class, 'awardBadge']);

    // Dashboard Analytics
    Route::get('/dashboard/stats', [\App\Http\Controllers\Api\DashboardController::class, 'stats']);

    // Monitoring
    Route::prefix('monitoring')->group(function() {
        Route::get('/daily', [\App\Http\Controllers\Api\MonitoringController::class, 'daily']);
        Route::get('/weekly', [\App\Http\Controllers\Api\MonitoringController::class, 'weekly']);
        Route::get('/monthly', [\App\Http\Controllers\Api\MonitoringController::class, 'monthly']);
        Route::get('/semester', [\App\Http\Controllers\Api\MonitoringController::class, 'semester']);
        Route::get('/class-comparison', [\App\Http\Controllers\Api\MonitoringController::class, 'classComparison']);
    });

    // Reports / Recap
    Route::get('/reports/student/{studentId}', [\App\Http\Controllers\Api\ReportController::class, 'getStudentReport']);
    Route::get('/reports/export', [\App\Http\Controllers\Api\ReportController::class, 'exportExcel']);

    // Evaluation
    Route::middleware(['role:guru,admin,superadmin'])->group(function() {
        Route::get('/evaluations', [\App\Http\Controllers\Api\EvaluationController::class, 'index']);
        Route::post('/evaluations/{id}/submit', [\App\Http\Controllers\Api\EvaluationController::class, 'submit']);
    });

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);

    // Announcements
    Route::apiResource('announcements', \App\Http\Controllers\Api\AnnouncementController::class);

    // Unused Models Endpoints
    Route::middleware(['role:admin,superadmin'])->group(function() {
        Route::get('/settings', [\App\Http\Controllers\Api\SettingController::class, 'index']);
        Route::post('/settings', [\App\Http\Controllers\Api\SettingController::class, 'store']);
        Route::apiResource('best-practices', \App\Http\Controllers\Api\BestPracticeController::class);
        Route::apiResource('dashboard-widgets', \App\Http\Controllers\Api\DashboardWidgetController::class);
        Route::apiResource('modules', \App\Http\Controllers\Api\ModuleController::class);
        Route::apiResource('teacher-reviews', \App\Http\Controllers\Api\TeacherReviewController::class);
        Route::apiResource('statistics', \App\Http\Controllers\Api\StatisticController::class);
        Route::apiResource('recaps', \App\Http\Controllers\Api\RecapController::class);
    });

    // Audit & Activity Logs
    Route::get('/activity-logs', [\App\Http\Controllers\Api\AuditLogController::class, 'activityLogs']);
    Route::get('/audit-logs', [\App\Http\Controllers\Api\AuditLogController::class, 'auditLogs']);
});
