<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Doctor\MedicalRecordController as DoctorMedicalRecordController;
use App\Http\Controllers\Doctor\PrescriptionController as DoctorPrescriptionController;
use App\Http\Controllers\Patient\AIDiagnosisController;
use App\Http\Controllers\Patient\MedicalRecordController;
use App\Http\Controllers\Patient\PatientController;
use App\Http\Controllers\Patient\PrescriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — 肿瘤科智能检测门诊系统
|--------------------------------------------------------------------------
|
| 认证方式：Bearer Token (自定义 Sanctum Guard)
| 统一前缀：/api
|
*/

// ───────────────────── 认证模块 ─────────────────────
// 前缀：/api/auth | 接口数：7

Route::prefix('auth')->group(function () {
    // 公开接口
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // 需认证接口
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/password', [AuthController::class, 'changePassword']);
        Route::post('/avatar', [AuthController::class, 'uploadAvatar']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
    });
});

// ───────────────────── 患者端 ─────────────────────
// 前缀：/api/patient | 接口数：7 | 角色：patient

Route::prefix('patient')
    ->middleware(['auth:sanctum', 'role:patient'])
    ->group(function () {
        Route::get('/dashboard', [PatientController::class, 'dashboard']);
        Route::get('/doctors', [PatientController::class, 'doctors']);
        Route::get('/doctors/{id}', [PatientController::class, 'doctorDetail']);
        Route::post('/appointments', [PatientController::class, 'store']);
        Route::get('/appointments', [PatientController::class, 'index']);
        Route::get('/appointments/{id}', [PatientController::class, 'show']);
        Route::delete('/appointments/{id}', [PatientController::class, 'cancel']);

        // AI 文字诊断
        Route::post('/ai-diagnosis', [AIDiagnosisController::class, 'store']);
        Route::get('/ai-diagnosis', [AIDiagnosisController::class, 'index']);
        Route::get('/ai-diagnosis/{id}', [AIDiagnosisController::class, 'show']);

        // 病历
        Route::get('/medical-records', [MedicalRecordController::class, 'index']);
        Route::get('/medical-records/{id}', [MedicalRecordController::class, 'show']);

        // 处方
        Route::get('/prescriptions', [PrescriptionController::class, 'index']);
        Route::get('/prescriptions/{id}', [PrescriptionController::class, 'show']);
        Route::post('/prescriptions/{id}/confirm', [PrescriptionController::class, 'confirm']);
    });

// ───────────────────── 医生端 ─────────────────────
// 前缀：/api/doctor | 接口数：7 | 角色：doctor

Route::prefix('doctor')
    ->middleware(['auth:sanctum', 'role:doctor'])
    ->group(function () {
        // 病历
        Route::post('/medical-records', [DoctorMedicalRecordController::class, 'store']);
        Route::put('/medical-records/{id}', [DoctorMedicalRecordController::class, 'update']);
        Route::get('/medical-records/{id}', [DoctorMedicalRecordController::class, 'show']);
        Route::get('/medical-records', [DoctorMedicalRecordController::class, 'index']);

        // 处方
        Route::post('/prescriptions', [DoctorPrescriptionController::class, 'store']);
        Route::get('/prescriptions/{id}', [DoctorPrescriptionController::class, 'show']);
        Route::get('/prescriptions', [DoctorPrescriptionController::class, 'index']);
    });
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
