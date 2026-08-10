<?php

namespace App\Http\Services\GYZ;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\Drug;
use App\Models\DrugStock;
use App\Models\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DrugService
{
    /** 低库存阈值 */
    public const LOW_STOCK_THRESHOLD = 10;

    /**
     * 药品库存列表
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = Drug::select(['id', 'name', 'category', 'specification', 'unit', 'stock_quantity', 'price', 'description', 'created_at', 'updated_at']);

        if (! empty($filters['keyword'])) {
            $query->where('name', 'like', "%{$filters['keyword']}%");
        }
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (filter_var($filters['low_stock'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->where('stock_quantity', '<', self::LOW_STOCK_THRESHOLD);
        }

        return $query->orderBy('id')
            ->paginate($filters['size'] ?? 10, page: $filters['page'] ?? 1)
            ->through(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'category' => $d->category,
                'specification' => $d->specification,
                'unit' => $d->unit,
                'stock_quantity' => $d->stock_quantity,
                'price' => $d->price,
                'description' => $d->description,
                'is_low_stock' => $d->stock_quantity < self::LOW_STOCK_THRESHOLD,
                'created_at' => $d->created_at->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
                'updated_at' => $d->updated_at->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 新增药品
     */
    public function create(array $data): array
    {
        if (Drug::where('name', $data['name'])->exists()) {
            throw new BusinessException('药品名称已存在', ResponseCode::DATA_DUPLICATE);
        }

        $drug = Drug::create($data);

        Log::channel('business')->info('新增药品', [
            'drug_id' => $drug->id,
            'name' => $drug->name,
        ]);

        return $this->format($drug);
    }

    /**
     * 编辑药品信息
     */
    public function update(int $id, array $data): array
    {
        $drug = Drug::find($id);
        if (! $drug) {
            throw new BusinessException('药品不存在', ResponseCode::DATA_NOT_FOUND);
        }
        if (empty($data)) {
            throw new BusinessException('至少提供一个更新字段', ResponseCode::PARAM_ERROR);
        }
        if (! empty($data['name']) && Drug::where('name', $data['name'])->where('id', '!=', $id)->exists()) {
            throw new BusinessException('药品名称已存在', ResponseCode::DATA_DUPLICATE);
        }

        $drug->update($data);

        return $this->format($drug);
    }

    /**
     * 药品入库（事务）
     */
    public function stockIn(int $id, int $quantity, ?string $remark, int $operatorId): array
    {
        return DB::transaction(function () use ($id, $quantity, $remark, $operatorId) {
            $drug = Drug::where('id', $id)->lockForUpdate()->first();
            if (! $drug) {
                throw new BusinessException('药品不存在', ResponseCode::DATA_NOT_FOUND);
            }

            $before = $drug->stock_quantity;
            $after = $before + $quantity;
            $drug->update(['stock_quantity' => $after]);

            // 同步 ZZT 的 drug_stocks 表，保持两套库存一致
            DrugStock::updateOrCreate(
                ['drug_id' => $drug->id],
                ['quantity' => $after]
            );

            $movement = StockMovement::create([
                'drug_id' => $drug->id,
                'type' => 'in',
                'quantity' => $quantity,
                'before_quantity' => $before,
                'after_quantity' => $after,
                'reference_type' => 'manual_stock_in',
                'remark' => $remark,
                'operator_id' => $operatorId,
                'created_at' => now(),
            ]);

            Log::channel('business')->info('药品入库', [
                'drug_id' => $drug->id,
                'quantity' => $quantity,
                'before' => $before,
                'after' => $after,
                'operator_id' => $operatorId,
            ]);

            return [
                'drug_id' => $drug->id,
                'drug_name' => $drug->name,
                'before_quantity' => $before,
                'after_quantity' => $after,
                'stock_movement_id' => $movement->id,
            ];
        });
    }

    private function format(Drug $drug): array
    {
        return [
            'id' => $drug->id,
            'name' => $drug->name,
            'category' => $drug->category,
            'specification' => $drug->specification,
            'unit' => $drug->unit,
            'stock_quantity' => $drug->stock_quantity,
            'price' => $drug->price,
            'description' => $drug->description,
            'created_at' => $drug->created_at->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
        ];
    }
}
