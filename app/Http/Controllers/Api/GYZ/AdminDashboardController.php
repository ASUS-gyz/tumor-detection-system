<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Http\Controllers\Controller;
use App\Http\Services\GYZ\AdminDashboardService;
use App\Http\Services\GYZ\DrugService;
use App\Http\Services\GYZ\StockMovementService;
use App\Support\PaginationHelper;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __construct(
        private AdminDashboardService $service,
        private DrugService $drugService,
        private StockMovementService $movementService,
    ) {}

    public function dashboard(): JsonResponse
    {
        return Result::success(data: $this->service->dashboard());
    }

    public function appointments(Request $request): JsonResponse
    {
        return Result::success(data: PaginationHelper::format(
            $this->service->appointments($request->only(['page', 'size', 'doctor_id', 'patient_name', 'status', 'date_from', 'date_to', 'sort']))
        ));
    }

    public function medicalRecords(Request $request): JsonResponse
    {
        return Result::success(data: PaginationHelper::format(
            $this->service->medicalRecords($request->only(['page', 'size', 'doctor_id', 'patient_name', 'date_from', 'date_to', 'sort']))
        ));
    }

    public function prescriptions(Request $request): JsonResponse
    {
        return Result::success(data: PaginationHelper::format(
            $this->service->prescriptions($request->only(['page', 'size', 'doctor_id', 'patient_name', 'status', 'date_from', 'date_to', 'sort']))
        ));
    }

    public function aiDiagnoses(Request $request): JsonResponse
    {
        return Result::success(data: PaginationHelper::format(
            $this->service->aiDiagnoses($request->only(['page', 'size', 'type', 'patient_name', 'date_from', 'date_to', 'sort']))
        ));
    }

    public function drugs(Request $request): JsonResponse
    {
        return Result::success(data: PaginationHelper::format(
            $this->drugService->list($request->only(['page', 'size', 'keyword', 'category', 'low_stock']))
        ));
    }

    public function stockMovements(Request $request): JsonResponse
    {
        return Result::success(data: PaginationHelper::format(
            $this->movementService->list($request->only(['page', 'size', 'drug_id', 'type', 'date_from', 'date_to']))
        ));
    }
}
