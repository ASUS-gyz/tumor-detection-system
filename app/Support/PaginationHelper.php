<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 分页格式化 — 统一转为 API 文档规范格式
 *
 * 输入 Laravel paginator → 输出 {list, page, size, total, total_pages}
 */
class PaginationHelper
{
    public static function format(LengthAwarePaginator $paginator): array
    {
        return [
            'list' => $paginator->items(),
            'page' => $paginator->currentPage(),
            'size' => (int) $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }
}
