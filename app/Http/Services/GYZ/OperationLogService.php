<?php

namespace App\Http\Services\GYZ;

use App\Models\OperationLog;
use Illuminate\Pagination\LengthAwarePaginator;

class OperationLogService
{
    /**
     * 记录操作日志
     */
    public static function log(string $action, string $module, ?string $targetType = null, ?int $targetId = null, ?string $content = null): void
    {
        $user = auth()->user();

        OperationLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? '系统',
            'action' => $action,
            'module' => $module,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'content' => $content,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * 操作日志列表
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return OperationLog::select(['id', 'user_id', 'user_name', 'action', 'module', 'target_type', 'target_id', 'content', 'ip', 'created_at'])
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['module'] ?? null, fn ($q, $v) => $q->where('module', $v))
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate($filters['size'] ?? 20, page: $filters['page'] ?? 1)
            ->through(fn ($l) => [
                'id' => $l->id,
                'user_id' => $l->user_id,
                'user_name' => $l->user_name,
                'action' => $l->action,
                'module' => $l->module,
                'target_type' => $l->target_type,
                'target_id' => $l->target_id,
                'content' => $l->content,
                'ip' => $l->ip,
                'created_at' => $l->created_at->toIso8601String(),
            ]);
    }
}
