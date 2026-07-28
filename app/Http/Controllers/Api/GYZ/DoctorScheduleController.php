<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Http\Controllers\Controller;
use App\Http\Services\GYZ\DoctorScheduleService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorScheduleController extends Controller
{
    public function __construct(private DoctorScheduleService $service) {}

    /**
     * 查看医生排班
     */
    public function index(): JsonResponse
    {
        return Result::success(data: $this->service->get(auth()->id()));
    }

    /**
     * 设置某天排班
     */
    public function update(Request $request): JsonResponse
    {
        return Result::success(msg: '排班已更新', data: $this->service->set(
            auth()->id(),
            $request->integer('day_of_week', 0),
            $request->only(['is_available', 'time_slots', 'max_patients'])
        ));
    }
}
