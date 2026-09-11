<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Http\Controllers\Controller;
use App\Http\Requests\GYZ\AiImageDiagnosisRequest;
use App\Http\Services\GYZ\DoctorAiDiagnosisService;
use App\Support\PaginationHelper;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DoctorAiDiagnosisController extends Controller
{
    public function __construct(private DoctorAiDiagnosisService $service) {}

    /**
     * 影像文件下载（私有磁盘）：仅限携带有效签名 URL 访问，无需登录态
     */
    public function serveImage(string $file): StreamedResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9._-]+$/', $file) && Storage::disk('local')->exists('ai-images/'.$file), 404);

        return Storage::disk('local')->response('ai-images/'.$file);
    }

    public function store(AiImageDiagnosisRequest $request): JsonResponse
    {
        return Result::success(msg: 'AI图文诊断完成', data: $this->service->create(
            auth()->id(),
            $request->integer('patient_id'),
            $request->filled('appointment_id') ? $request->integer('appointment_id') : null,
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
