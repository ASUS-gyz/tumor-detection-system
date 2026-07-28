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
