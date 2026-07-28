<?php

namespace App\Http\Services\GYZ;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class AdminUserService
{
    /**
     * 用户列表
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = User::select(['id', 'name', 'email', 'role', 'phone', 'status', 'created_at']);

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['keyword'])) {
            $query->where(fn ($w) => $w->where('name', 'like', "%{$filters['keyword']}%")
                ->orWhere('email', 'like', "%{$filters['keyword']}%"));
        }

        return $query->orderBy('id')
            ->paginate($filters['size'] ?? 10, page: $filters['page'] ?? 1)
            ->through(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'phone' => $u->phone,
                'status' => $u->status,
                'created_at' => $u->created_at?->toIso8601String(),
            ]);
    }

    /**
     * 创建用户（医生/管理员）
     */
    public function create(array $data): array
    {
        if (! in_array($data['role'], ['doctor', 'admin'])) {
            throw new BusinessException('角色只能为 doctor 或 admin', ResponseCode::PARAM_OUT_OF_RANGE);
        }
        if (User::where('email', $data['email'])->exists()) {
            throw new BusinessException('邮箱已被注册', ResponseCode::DATA_DUPLICATE);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'phone' => $data['phone'] ?? null,
            'status' => 'active',
        ]);

        Log::channel('business')->warning('管理员创建用户', [
            'operator_id' => auth()->id(),
            'created_user_id' => $user->id,
            'role' => $user->role,
        ]);

        return $this->format($user);
    }

    /**
     * 用户详情
     */
    public function detail(int $id): array
    {
        return $this->format(User::find($id) ?? throw new BusinessException('用户不存在', ResponseCode::DATA_NOT_FOUND));
    }

    /**
     * 编辑用户信息
     */
    public function update(int $id, array $data, int $selfId): array
    {
        $user = User::find($id) ?? throw new BusinessException('用户不存在', ResponseCode::DATA_NOT_FOUND);

        if (isset($data['email']) && $data['email'] !== $user->email) {
            if (User::where('email', $data['email'])->where('id', '!=', $id)->exists()) {
                throw new BusinessException('邮箱已被注册', ResponseCode::DATA_DUPLICATE);
            }
        }

        if (isset($data['role']) && $id === $selfId) {
            throw new BusinessException('不允许修改自己的角色', ResponseCode::FORBIDDEN);
        }

        $user->update(array_filter($data, fn ($v) => $v !== null));

        return $this->format($user);
    }

    /**
     * 启用/禁用
     */
    public function toggleStatus(int $id, string $status, int $selfId): array
    {
        if ($id === $selfId) {
            throw new BusinessException('不允许操作自己的账号', ResponseCode::STATUS_NOT_ALLOWED);
        }

        $user = User::find($id) ?? throw new BusinessException('用户不存在', ResponseCode::DATA_NOT_FOUND);
        $user->update(['status' => $status]);

        Log::channel('business')->warning('管理员变更用户状态', [
            'operator_id' => $selfId,
            'target_user_id' => $id,
            'new_status' => $status,
        ]);

        return $this->format($user);
    }

    private function format(User $user): array
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'phone' => $user->phone,
            'status' => $user->status,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];

        if ($user->isDoctor()) {
            $data['title'] = $user->title;
            $data['specialty'] = $user->specialty;
            $data['department'] = $user->department;
            $data['introduction'] = $user->introduction;
            $data['experience_years'] = $user->experience_years;
        }

        return $data;
    }
}
