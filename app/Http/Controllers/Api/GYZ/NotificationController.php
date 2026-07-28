<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Http\Controllers\Controller;
use App\Http\Services\GYZ\NotificationService;
use App\Support\PaginationHelper;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $service) {}

    /**
     * 通知列表
     */
    public function index(Request $request): JsonResponse
    {
        return Result::success(data: PaginationHelper::format(
            $this->service->list(auth()->id(), $request->only(['page', 'size', 'is_read']))
        ));
    }

    /**
     * 未读数量
     */
    public function unreadCount(): JsonResponse
    {
        return Result::success(data: ['count' => $this->service->unreadCount(auth()->id())]);
    }

    /**
     * 标记已读
     */
    public function markRead(int $id): JsonResponse
    {
        $this->service->markRead(auth()->id(), $id);

        return Result::success(msg: '已标记为已读');
    }

    /**
     * 全部已读
     */
    public function markAllRead(): JsonResponse
    {
        $this->service->markAllRead(auth()->id());

        return Result::success(msg: '全部已标记为已读');
    }
}
