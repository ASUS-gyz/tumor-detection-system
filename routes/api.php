<?php

use App\Http\Controllers\Api\GYZ\AdminDashboardController;
use App\Http\Controllers\Api\GYZ\AdminUserController;
use App\Http\Controllers\Api\GYZ\DoctorAiDiagnosisController;
use App\Http\Controllers\Api\GYZ\DoctorAppointmentController;
use App\Http\Controllers\Api\GYZ\DrugController;
use Illuminate\Support\Facades\Route;

//GYZ

// 医生端-接诊管理
Route::middleware(['auth:sanctum', 'role:doctor'])->prefix('doctor')->name('doctor.')->group(function () {
    Route::get('/dashboard', [DoctorAppointmentController::class, 'dashboard']);           // 6.2
    Route::get('/appointments', [DoctorAppointmentController::class, 'index']);            // 6.3
    Route::get('/appointments/{id}', [DoctorAppointmentController::class, 'show']);        // 6.4
    Route::post('/appointments/{id}/call', [DoctorAppointmentController::class, 'call']);  // 6.5
    Route::post('/appointments/{id}/start', [DoctorAppointmentController::class, 'start']);// 6.6
    Route::post('/appointments/{id}/complete', [DoctorAppointmentController::class, 'complete']); // 6.7
});

// 医生端-AI图文诊断
Route::middleware(['auth:sanctum', 'role:doctor'])->prefix('doctor')->name('doctor.')->group(function () {
    Route::post('/ai-diagnosis', [DoctorAiDiagnosisController::class, 'store']);           // 8.2
    Route::get('/ai-diagnosis', [DoctorAiDiagnosisController::class, 'index']);            // 8.3
    Route::get('/ai-diagnosis/{id}', [DoctorAiDiagnosisController::class, 'show']);        // 8.4
});

// 医生端-药品库存管理
Route::middleware(['auth:sanctum', 'role:doctor'])->prefix('doctor')->name('doctor.')->group(function () {
    Route::get('/drugs', [DrugController::class, 'index']);                                // 9.2
    Route::post('/drugs', [DrugController::class, 'store']);                               // 9.3
    Route::put('/drugs/{id}', [DrugController::class, 'update']);                          // 9.4
    Route::post('/drugs/{id}/stock-in', [DrugController::class, 'stockIn']);               // 9.5
    Route::get('/stock-movements', [DrugController::class, 'stockMovements']);             // 9.6
});

// 管理员端-用户管理
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index']);                           // 10.2
    Route::post('/users', [AdminUserController::class, 'store']);                          // 10.3
    Route::get('/users/{id}', [AdminUserController::class, 'show']);                       // 10.4
    Route::put('/users/{id}', [AdminUserController::class, 'update']);                     // 10.5
    Route::put('/users/{id}/status', [AdminUserController::class, 'toggleStatus']);        // 10.6
});

// 管理员端-数据监控
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'dashboard']);              // 11.2
    Route::get('/appointments', [AdminDashboardController::class, 'appointments']);        // 11.3
    Route::get('/medical-records', [AdminDashboardController::class, 'medicalRecords']);   // 11.4
    Route::get('/prescriptions', [AdminDashboardController::class, 'prescriptions']);      // 11.5
    Route::get('/ai-diagnoses', [AdminDashboardController::class, 'aiDiagnoses']);         // 11.6
    Route::get('/drugs', [AdminDashboardController::class, 'drugs']);                      // 11.7
    Route::get('/stock-movements', [AdminDashboardController::class, 'stockMovements']);   // 11.8
});
