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
    
    // Master Data Routes
    Route::prefix('master')->group(function() {
        Route::apiResource('students', \App\Http\Controllers\Api\Master\StudentController::class);
        Route::apiResource('teachers', \App\Http\Controllers\Api\Master\TeacherController::class);
        Route::apiResource('habits', \App\Http\Controllers\Api\Master\HabitController::class);
        Route::apiResource('academic-years', \App\Http\Controllers\Api\Master\AcademicYearController::class);
        Route::apiResource('classes', \App\Http\Controllers\Api\Master\SchoolClassController::class);
    });

    // Transaction Routes
    Route::prefix('journals')->group(function() {
        Route::get('/', [\App\Http\Controllers\Api\Transaction\JournalController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\Transaction\JournalController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Api\Transaction\JournalController::class, 'show']);
        
        // Approvals
        Route::post('/{id}/approve-teacher', [\App\Http\Controllers\Api\Transaction\ApprovalController::class, 'approveByTeacher']);
        Route::post('/{id}/approve-parent', [\App\Http\Controllers\Api\Transaction\ApprovalController::class, 'approveByParent']);
    });

    // Dashboard Analytics
    Route::get('/dashboard/stats', [\App\Http\Controllers\Api\DashboardController::class, 'stats']);

    // Reports / Recap
    Route::get('/reports/student/{studentId}', [\App\Http\Controllers\Api\ReportController::class, 'getStudentReport']);

    // Evaluation
    Route::get('/evaluations', [\App\Http\Controllers\Api\EvaluationController::class, 'index']);
    Route::post('/evaluations/{id}/submit', [\App\Http\Controllers\Api\EvaluationController::class, 'submit']);
});
