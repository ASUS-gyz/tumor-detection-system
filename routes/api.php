<?php

use App\Http\Controllers\Api\GYZ\AdminDashboardController;
use App\Http\Controllers\Api\GYZ\AdminSystemController;
use App\Http\Controllers\Api\GYZ\AdminUserController;
use App\Http\Controllers\Api\GYZ\DoctorAiDiagnosisController;
use App\Http\Controllers\Api\GYZ\DoctorAppointmentController;
use App\Http\Controllers\Api\GYZ\DoctorScheduleController;
use App\Http\Controllers\Api\GYZ\DrugController;
use App\Http\Controllers\Api\GYZ\NotificationController;
use App\Http\Controllers\Api\ZZT\AuthController;
use App\Http\Controllers\Api\ZZT\MedicalRecordController as DoctorMedicalRecordController;
use App\Http\Controllers\Api\ZZT\PrescriptionController as DoctorPrescriptionController;
use App\Http\Controllers\Api\ZZT\TemplateController;
use App\Http\Controllers\Api\ZZT\AIDiagnosisController;
use App\Http\Controllers\Api\ZZT\MedicalRecordController;
use App\Http\Controllers\Api\ZZT\PatientController;
use App\Http\Controllers\Api\ZZT\PrescriptionController;
use Illuminate\Support\Facades\Route;

//ZZT

// 认证模块
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/password', [AuthController::class, 'changePassword']);
        Route::post('/avatar', [AuthController::class, 'uploadAvatar']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::delete('/account', [AuthController::class, 'deleteAccount']);
    });
});

// 患者端
Route::prefix('patient')->middleware(['auth:sanctum', 'role:patient'])->group(function () {
    // 预约管理
    Route::get('/dashboard', [PatientController::class, 'dashboard']);
    Route::get('/doctors', [PatientController::class, 'doctors']);
    Route::get('/doctors/{id}', [PatientController::class, 'doctorDetail']);
    Route::post('/appointments', [PatientController::class, 'store']);
    Route::get('/appointments', [PatientController::class, 'index']);
    Route::get('/appointments/available-slots', [PatientController::class, 'availableSlots']);
    Route::get('/appointments/{id}', [PatientController::class, 'show']);
    Route::delete('/appointments/{id}', [PatientController::class, 'cancel']);
    Route::post('/appointments/{id}/review', [PatientController::class, 'review']);

    // AI 文字诊断
    Route::post('/ai-diagnosis', [AIDiagnosisController::class, 'store']);
    Route::get('/ai-diagnosis', [AIDiagnosisController::class, 'index']);
    Route::post('/ai-diagnosis/continue', [AIDiagnosisController::class, 'continue']);
    Route::get('/ai-diagnosis/{id}', [AIDiagnosisController::class, 'show']);
    Route::get('/ai-diagnosis/{id}/export', [AIDiagnosisController::class, 'exportPdf']);
Route::prefix('patient')
    ->middleware(['auth:sanctum', 'role:patient'])
    ->group(function () {
        // 预约管理
        Route::get('/dashboard', [PatientController::class, 'dashboard']);
        Route::get('/doctors', [PatientController::class, 'doctors']);
        Route::get('/doctors/{id}', [PatientController::class, 'doctorDetail']);
        Route::post('/appointments', [PatientController::class, 'store']);
        Route::get('/appointments', [PatientController::class, 'index']);
        Route::get('/appointments/available-slots', [PatientController::class, 'availableSlots']);
        Route::get('/appointments/{id}', [PatientController::class, 'show']);
        Route::delete('/appointments/{id}', [PatientController::class, 'cancel']);
        Route::post('/appointments/{id}/review', [PatientController::class, 'review']);

    // 病历
    Route::get('/medical-records', [MedicalRecordController::class, 'index']);
    Route::get('/medical-records/{id}', [MedicalRecordController::class, 'show']);

    // 处方
    Route::get('/prescriptions', [PrescriptionController::class, 'index']);
    Route::get('/prescriptions/{id}', [PrescriptionController::class, 'show']);
    Route::post('/prescriptions/{id}/confirm', [PrescriptionController::class, 'confirm']);
    Route::post('/prescriptions/{id}/refill', [PrescriptionController::class, 'refill']);
    Route::get('/medication-reminders', [PrescriptionController::class, 'medicationReminders']);
});

// 医生端-病历与处方
Route::prefix('doctor')->middleware(['auth:sanctum', 'role:doctor'])->group(function () {
    Route::post('/medical-records', [DoctorMedicalRecordController::class, 'store']);
    Route::put('/medical-records/{id}', [DoctorMedicalRecordController::class, 'update']);
    Route::get('/medical-records/{id}', [DoctorMedicalRecordController::class, 'show']);
    Route::get('/medical-records', [DoctorMedicalRecordController::class, 'index']);
    Route::get('/medical-records/compare', [DoctorMedicalRecordController::class, 'compare']);
    Route::post('/prescriptions', [DoctorPrescriptionController::class, 'store']);
    Route::get('/prescriptions/{id}', [DoctorPrescriptionController::class, 'show']);
    Route::get('/prescriptions', [DoctorPrescriptionController::class, 'index']);

    // 模板
    Route::post('/medical-record-templates', [TemplateController::class, 'storeMedicalRecord']);
    Route::get('/medical-record-templates', [TemplateController::class, 'indexMedicalRecord']);
    Route::post('/prescription-templates', [TemplateController::class, 'storePrescription']);
    Route::get('/prescription-templates', [TemplateController::class, 'indexPrescription']);
});

//GYZ

// 医生端-接诊管理
Route::middleware(['auth:sanctum', 'role:doctor'])->prefix('doctor')->group(function () {
    Route::get('/dashboard', [DoctorAppointmentController::class, 'dashboard']);
    Route::get('/appointments', [DoctorAppointmentController::class, 'index']);
    Route::get('/appointments/{id}', [DoctorAppointmentController::class, 'show']);
    Route::post('/appointments/{id}/call', [DoctorAppointmentController::class, 'call']);
    Route::post('/appointments/{id}/start', [DoctorAppointmentController::class, 'start']);
    Route::post('/appointments/{id}/complete', [DoctorAppointmentController::class, 'complete']);
    Route::post('/appointments/{id}/reject', [DoctorAppointmentController::class, 'reject']);
    Route::get('/schedules', [DoctorScheduleController::class, 'index']);
    Route::put('/schedules', [DoctorScheduleController::class, 'update']);
});

// 医生端-AI图文诊断
Route::middleware(['auth:sanctum', 'role:doctor'])->prefix('doctor')->group(function () {
    Route::post('/ai-diagnosis', [DoctorAiDiagnosisController::class, 'store']);
    Route::get('/ai-diagnosis', [DoctorAiDiagnosisController::class, 'index']);
    Route::get('/ai-diagnosis/{id}', [DoctorAiDiagnosisController::class, 'show']);
});

// 医生端-药品库存管理
Route::middleware(['auth:sanctum', 'role:doctor'])->prefix('doctor')->group(function () {
    Route::get('/drugs', [DrugController::class, 'index']);
    Route::post('/drugs', [DrugController::class, 'store']);
    Route::put('/drugs/{id}', [DrugController::class, 'update']);
    Route::post('/drugs/{id}/stock-in', [DrugController::class, 'stockIn']);
    Route::get('/stock-movements', [DrugController::class, 'stockMovements']);
    Route::get('/drugs/low-stock', [DrugController::class, 'lowStock']);
    Route::post('/drugs/batch-stock-in', [DrugController::class, 'batchStockIn']);
});

// 通知消息
Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('/{id}/read', [NotificationController::class, 'markRead']);
    Route::put('/read-all', [NotificationController::class, 'markAllRead']);
});

// 管理员端-用户管理
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::put('/users/{id}/status', [AdminUserController::class, 'toggleStatus']);
    Route::get('/users/{id}/operation-logs', [AdminSystemController::class, 'operationLogs']);
    Route::post('/users/batch-import', [AdminUserController::class, 'batchImport']);
});

// 管理员端-数据监控
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'dashboard']);
    Route::get('/appointments', [AdminDashboardController::class, 'appointments']);
    Route::get('/medical-records', [AdminDashboardController::class, 'medicalRecords']);
    Route::get('/prescriptions', [AdminDashboardController::class, 'prescriptions']);
    Route::get('/ai-diagnoses', [AdminDashboardController::class, 'aiDiagnoses']);
    Route::get('/drugs', [AdminDashboardController::class, 'drugs']);
    Route::get('/stock-movements', [AdminDashboardController::class, 'stockMovements']);
    Route::get('/statistics/doctor-workload', [AdminSystemController::class, 'doctorWorkload']);
    Route::get('/statistics/drug-consumption', [AdminSystemController::class, 'drugConsumption']);
    Route::get('/statistics/monthly-trend', [AdminSystemController::class, 'monthlyTrend']);
    Route::get('/statistics/drug-overview', [AdminSystemController::class, 'drugOverview']);
});

// 管理员端-系统管理
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/operation-logs', [AdminSystemController::class, 'operationLogs']);
    Route::get('/system-configs', [AdminSystemController::class, 'configs']);
    Route::put('/system-configs', [AdminSystemController::class, 'updateConfigs']);
});
