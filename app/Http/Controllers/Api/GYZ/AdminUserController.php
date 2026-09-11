<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Http\Controllers\Controller;
use App\Exceptions\BusinessException;
use App\Http\Requests\GYZ\AdminUserCreateRequest;
use App\Http\Requests\GYZ\AdminUserStatusRequest;
use App\Http\Requests\GYZ\AdminUserUpdateRequest;
use App\Http\Services\GYZ\AdminUserService;
use App\Http\Services\GYZ\OperationLogService;
use App\Support\PaginationHelper;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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

    public function batchImport(Request $request): JsonResponse
    {
        $request->validate(['users' => 'required|array|min:1|max:200']);

        // 逐条复用单条创建校验规则；单条失败不影响其余项，返回逐条成功/失败清单
        $rules = (new AdminUserCreateRequest())->rules();
        $results = []; $created = 0;
        foreach ($request->input('users') as $idx => $userData) {
            $v = Validator::make((array) $userData, $rules);
            if ($v->fails()) {
                $results[] = ['index' => $idx, 'ok' => false, 'error' => $v->errors()->first()];
                continue;
            }
            try {
                $u = $this->service->create($v->validated());
                $results[] = ['index' => $idx, 'ok' => true, 'user_id' => $u['id'], 'email' => $u['email']];
                $created++;
            } catch (BusinessException $e) {
                $results[] = ['index' => $idx, 'ok' => false, 'error' => $e->getMessage()];
            } catch (\Throwable $e) {
                Log::error('批量导入单项失败', ['index' => $idx, 'error' => $e->getMessage()]);
                $results[] = ['index' => $idx, 'ok' => false, 'error' => '系统异常'];
            }
        }
        $failed = count($results) - $created;

        OperationLogService::log('create', 'user', null, null, "批量导入账号：成功 {$created} 个，失败 {$failed} 个");

        return Result::success(
            msg: $failed === 0 ? "成功导入 {$created} 个账号" : "批量导入完成：成功 {$created} 个，失败 {$failed} 个",
            data: ['total' => count($results), 'success' => $created, 'failed' => $failed, 'results' => $results]
        );
    }
}
