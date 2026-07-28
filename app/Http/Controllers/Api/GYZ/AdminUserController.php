<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Http\Controllers\Controller;
use App\Http\Requests\GYZ\AdminUserCreateRequest;
use App\Http\Requests\GYZ\AdminUserStatusRequest;
use App\Http\Requests\GYZ\AdminUserUpdateRequest;
use App\Http\Services\GYZ\AdminUserService;
use App\Support\PaginationHelper;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(private AdminUserService $service) {}

    public function index(Request $request): JsonResponse
    {
        return Result::success(data: PaginationHelper::format(
            $this->service->list($request->only(['page', 'size', 'role', 'status', 'keyword']))
        ));
    }

    public function store(AdminUserCreateRequest $request): JsonResponse
    {
        return Result::success(msg: '账号创建成功', data: $this->service->create($request->validated()));
    }

    public function show(int $id): JsonResponse
    {
        return Result::success(data: $this->service->detail($id));
    }

    public function update(AdminUserUpdateRequest $request, int $id): JsonResponse
    {
        return Result::success(msg: '用户信息已更新', data: $this->service->update($id, $request->validated(), auth()->id()));
    }

    public function toggleStatus(AdminUserStatusRequest $request, int $id): JsonResponse
    {
        return Result::success(msg: '状态已更新', data: $this->service->toggleStatus($id, $request->input('status'), auth()->id()));
    }
}
