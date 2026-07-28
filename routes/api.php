<?php

use App\Http\Controllers\Api\GYZ\AdminDashboardController;
use App\Http\Controllers\Api\GYZ\AdminSystemController;
use App\Http\Controllers\Api\GYZ\AdminUserController;
use App\Http\Controllers\Api\GYZ\DoctorAiDiagnosisController;
use App\Http\Controllers\Api\GYZ\DoctorAppointmentController;
use App\Http\Controllers\Api\GYZ\DoctorScheduleController;
use App\Http\Controllers\Api\GYZ\DrugController;
use App\Http\Controllers\Api\GYZ\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — GYZ 模块（26 + 13 = 39 接口）
|--------------------------------------------------------------------------
*/

// ===== 医生端-接诊管理（8 接口）=====
Route::middleware(['auth:sanctum', 'role:doctor'])->prefix('doctor')->name('doctor.')->group(function () {
    Route::get('/dashboard', [DoctorAppointmentController::class, 'dashboard']);
    Route::get('/appointments', [DoctorAppointmentController::class, 'index']);
    Route::get('/appointments/{id}', [DoctorAppointmentController::class, 'show']);
    Route::post('/appointments/{id}/call', [DoctorAppointmentController::class, 'call']);
    Route::post('/appointments/{id}/start', [DoctorAppointmentController::class, 'start']);
    Route::post('/appointments/{id}/complete', [DoctorAppointmentController::class, 'complete']);
    Route::post('/appointments/{id}/reject', [DoctorAppointmentController::class, 'reject']);  // 新增
    // 排班管理
    Route::get('/schedules', [DoctorScheduleController::class, 'index']);      // 新增
    Route::put('/schedules', [DoctorScheduleController::class, 'update']);     // 新增
});

// ===== 医生端-AI图文诊断（3 接口）=====
Route::middleware(['auth:sanctum', 'role:doctor'])->prefix('doctor')->name('doctor.')->group(function () {
    Route::post('/ai-diagnosis', [DoctorAiDiagnosisController::class, 'store']);
    Route::get('/ai-diagnosis', [DoctorAiDiagnosisController::class, 'index']);
    Route::get('/ai-diagnosis/{id}', [DoctorAiDiagnosisController::class, 'show']);
});

// ===== 医生端-药品库存管理（7 接口）=====
Route::middleware(['auth:sanctum', 'role:doctor'])->prefix('doctor')->name('doctor.')->group(function () {
    Route::get('/drugs', [DrugController::class, 'index']);
    Route::post('/drugs', [DrugController::class, 'store']);
    Route::put('/drugs/{id}', [DrugController::class, 'update']);
    Route::post('/drugs/{id}/stock-in', [DrugController::class, 'stockIn']);
    Route::get('/stock-movements', [DrugController::class, 'stockMovements']);
    Route::get('/drugs/low-stock', [DrugController::class, 'lowStock']);       // 新增
    Route::post('/drugs/batch-stock-in', [DrugController::class, 'batchStockIn']); // 新增
});

// ===== 通知消息（4 接口，所有已登录角色）=====
Route::middleware('auth:sanctum')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);                 // 新增
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']); // 新增
    Route::put('/{id}/read', [NotificationController::class, 'markRead']);     // 新增
    Route::put('/read-all', [NotificationController::class, 'markAllRead']);   // 新增
});

// ===== 管理员端-用户管理（7 接口）=====
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::put('/users/{id}/status', [AdminUserController::class, 'toggleStatus']);
    Route::get('/users/{id}/operation-logs', [AdminSystemController::class, 'operationLogs']); // 新增
    Route::post('/users/batch-import', [AdminUserController::class, 'batchImport']);            // 新增
});

// ===== 管理员端-数据监控（11 接口）=====
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'dashboard']);
    Route::get('/appointments', [AdminDashboardController::class, 'appointments']);
    Route::get('/medical-records', [AdminDashboardController::class, 'medicalRecords']);
    Route::get('/prescriptions', [AdminDashboardController::class, 'prescriptions']);
    Route::get('/ai-diagnoses', [AdminDashboardController::class, 'aiDiagnoses']);
    Route::get('/drugs', [AdminDashboardController::class, 'drugs']);
    Route::get('/stock-movements', [AdminDashboardController::class, 'stockMovements']);
    // 增强统计
    Route::get('/statistics/doctor-workload', [AdminSystemController::class, 'doctorWorkload']);   // 新增
    Route::get('/statistics/drug-consumption', [AdminSystemController::class, 'drugConsumption']); // 新增
    Route::get('/statistics/monthly-trend', [AdminSystemController::class, 'monthlyTrend']);       // 新增
    Route::get('/statistics/drug-overview', [AdminSystemController::class, 'drugOverview']);       // 新增
});

// ===== 管理员端-系统管理（3 接口）=====
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/operation-logs', [AdminSystemController::class, 'operationLogs']);      // 新增
    Route::get('/system-configs', [AdminSystemController::class, 'configs']);             // 新增
    Route::put('/system-configs', [AdminSystemController::class, 'updateConfigs']);       // 新增
});
