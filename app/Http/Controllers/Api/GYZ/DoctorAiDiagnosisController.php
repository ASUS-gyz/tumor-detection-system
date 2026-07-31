<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Http\Controllers\Controller;
use App\Http\Requests\GYZ\AiImageDiagnosisRequest;
use App\Http\Services\GYZ\DoctorAiDiagnosisService;
use App\Support\PaginationHelper;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorAiDiagnosisController extends Controller
{
    public function __construct(private DoctorAiDiagnosisService $service) {}

    public function store(AiImageDiagnosisRequest $request): JsonResponse
    {
        return Result::success(msg: 'AI图文诊断完成', data: $this->service->create(
            auth()->id(),
            $request->integer('patient_id'),
            $request->integer('appointment_id'),
            $request->file('image'),
            $request->input('description')
        ));
    }

    public function index(Request $request): JsonResponse
    {
        return Result::success(data: PaginationHelper::format(
            $this->service->list(auth()->id(), $request->only(['page', 'size', 'patient_name', 'date_from', 'date_to']))
        ));
    }

    public function show(int $id): JsonResponse
    {
        return Result::success(data: $this->service->detail(auth()->id(), $id));
    }
}
