<?php

namespace App\Http\Services\GYZ;

use App\Models\Notification;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    /**
     * 发送通知
     */
    public static function send(int $userId, string $type, string $title, string $content, ?string $refType = null, ?int $refId = null): void
    {
        Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'created_at' => now(),
        ]);
    }

    /**
     * 通知列表
     */
    public function list(int $userId, array $filters): LengthAwarePaginator
    {
        return Notification::where('user_id', $userId)
            ->select(['id', 'type', 'title', 'content', 'is_read', 'reference_type', 'reference_id', 'created_at'])
            ->when(isset($filters['is_read']), fn ($q) => $q->where('is_read', $filters['is_read']))
            ->latest()
            ->paginate($filters['size'] ?? 20, page: $filters['page'] ?? 1)
            ->through(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'content' => $n->content,
                'is_read' => $n->is_read,
                'reference_type' => $n->reference_type,
                'reference_id' => $n->reference_id,
                'created_at' => $n->created_at->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 未读数量
     */
    public function unreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)->where('is_read', false)->count();
    }

    /**
     * 标记已读
     */
    public function markRead(int $userId, int $id): void
    {
        Notification::where('user_id', $userId)->where('id', $id)->update(['is_read' => true]);
    }

    /**
     * 全部已读
     */
    public function markAllRead(int $userId): void
    {
        Notification::where('user_id', $userId)->where('is_read', false)->update(['is_read' => true]);
    }
}
