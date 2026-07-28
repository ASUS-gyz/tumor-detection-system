<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Http\Controllers\Controller;
use App\Http\Requests\GYZ\DoctorAppointmentListRequest;
use App\Http\Services\GYZ\DoctorAppointmentService;
use App\Support\PaginationHelper;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class DoctorAppointmentController extends Controller
{
    public function __construct(private DoctorAppointmentService $service) {}

    public function dashboard(): JsonResponse
    {
        return Result::success(data: $this->service->dashboard(auth()->id()));
    }

    public function index(DoctorAppointmentListRequest $request): JsonResponse
    {
        return Result::success(data: PaginationHelper::format(
            $this->service->appointmentList(auth()->id(), $request->validated())
        ));
    }

    public function show(int $id): JsonResponse
    {
        return Result::success(data: $this->service->appointmentDetail(auth()->id(), $id));
    }

    public function call(int $id): JsonResponse
    {
        return Result::success(msg: '叫号成功', data: $this->service->call(auth()->id(), $id));
    }

    public function start(int $id): JsonResponse
    {
        return Result::success(msg: '开始接诊', data: $this->service->start(auth()->id(), $id));
    }

    public function complete(int $id): JsonResponse
    {
        return Result::success(msg: '结束接诊', data: $this->service->complete(auth()->id(), $id));
    }
}
