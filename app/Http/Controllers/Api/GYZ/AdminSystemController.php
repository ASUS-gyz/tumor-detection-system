<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Http\Controllers\Controller;
use App\Http\Services\GYZ\AdminStatisticsService;
use App\Http\Services\GYZ\OperationLogService;
use App\Http\Services\GYZ\SystemConfigService;
use App\Support\PaginationHelper;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSystemController extends Controller
{
    public function __construct(
        private OperationLogService $logService,
        private SystemConfigService $configService,
        private AdminStatisticsService $statsService,
    ) {}

    // === 操作日志 ===

    public function operationLogs(Request $request): JsonResponse
    {
        // 当通过 /admin/users/{id}/operation-logs 访问时，自动用路由参数筛选用户
        if ($userId = $request->route('id')) {
            $request->merge(['user_id' => (int) $userId]);
        }

        return Result::success(data: PaginationHelper::format(
            $this->logService->list($request->only(['page', 'size', 'user_id', 'module', 'action', 'date_from', 'date_to']))
        ));
    }

    // === 系统配置 ===

    public function configs(): JsonResponse
    {
        return Result::success(data: $this->configService->all());
    }

    public function updateConfigs(Request $request): JsonResponse
    {
        return Result::success(msg: '配置已更新', data: $this->configService->update($request->input('configs', [])));
    }

    // === 综合统计 ===

    public function doctorWorkload(Request $request): JsonResponse
    {
        return Result::success(data: $this->statsService->doctorWorkload(
            $request->input('date_from'), $request->input('date_to')
        ));
    }

    public function drugConsumption(Request $request): JsonResponse
    {
        return Result::success(data: $this->statsService->drugConsumption(
            $request->input('date_from'), $request->input('date_to')
        ));
    }

    public function monthlyTrend(Request $request): JsonResponse
    {
        return Result::success(data: $this->statsService->monthlyTrend($request->integer('months', 6)));
    }

    public function drugOverview(): JsonResponse
    {
        return Result::success(data: $this->statsService->drugOverview());
    }
}
