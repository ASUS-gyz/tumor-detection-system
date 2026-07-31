<?php

namespace App\Http\Services\GYZ;

use App\Models\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;

class StockMovementService
{
    /**
     * 库存变动日志列表
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = StockMovement::with(['drug:id,name', 'operator:id,name'])
            ->select(['id', 'drug_id', 'type', 'quantity', 'before_quantity', 'after_quantity', 'reference_type', 'reference_id', 'remark', 'operator_id', 'created_at']);

        if (! empty($filters['drug_id'])) {
            $query->where('drug_id', $filters['drug_id']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->latest()
            ->paginate($filters['size'] ?? 10, page: $filters['page'] ?? 1)
            ->through(fn ($m) => [
                'id' => $m->id,
                'drug_id' => $m->drug_id,
                'drug_name' => $m->drug?->name,
                'type' => $m->type,
                'quantity' => $m->quantity,
                'before_quantity' => $m->before_quantity,
                'after_quantity' => $m->after_quantity,
                'reference_type' => $m->reference_type,
                'reference_id' => $m->reference_id,
                'remark' => $m->remark,
                'operator_name' => $m->operator?->name ?? '系统',
                'created_at' => $m->created_at->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
            ]);
    }
}
