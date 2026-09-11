<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Http\Controllers\Controller;
use App\Exceptions\BusinessException;
use App\Http\Requests\GYZ\DrugCreateRequest;
use App\Http\Requests\GYZ\DrugUpdateRequest;
use App\Http\Requests\GYZ\StockInRequest;
use App\Http\Services\GYZ\DrugService;
use App\Http\Services\GYZ\StockMovementService;
use App\Support\PaginationHelper;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DrugController extends Controller
{
    public function __construct(
        private DrugService $drugService,
        private StockMovementService $movementService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return Result::success(data: PaginationHelper::format(
            $this->drugService->list($request->only(['page', 'size', 'keyword', 'category', 'low_stock']))
        ));
    }

    public function store(DrugCreateRequest $request): JsonResponse
    {
        return Result::success(msg: '药品添加成功', data: $this->drugService->create($request->validated()));
    }

    public function update(DrugUpdateRequest $request, int $id): JsonResponse
    {
        return Result::success(msg: '药品信息已更新', data: $this->drugService->update($id, $request->validated()));
    }

    public function stockIn(StockInRequest $request, int $id): JsonResponse
    {
        return Result::success(msg: '入库成功', data: $this->drugService->stockIn(
            $id,
            $request->integer('quantity'),
            $request->input('remark'),
            auth()->id()
        ));
    }

    public function stockMovements(Request $request): JsonResponse
    {
        return Result::success(data: PaginationHelper::format(
            $this->movementService->list($request->only(['page', 'size', 'drug_id', 'type', 'date_from', 'date_to']))
        ));
    }

    public function lowStock(Request $request): JsonResponse
    {
        return Result::success(data: PaginationHelper::format(
            $this->drugService->list(array_merge($request->only(['page', 'size']), ['low_stock' => true]))
        ));
    }

    public function batchStockIn(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1|max:200',
            'items.*.drug_id' => 'required|integer|exists:drugs,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.remark' => 'nullable|string|max:255',
        ]);

        // 逐条隔离：单条失败不影响其余项，返回逐条成功/失败清单
        $results = []; $ok = 0;
        foreach ($request->input('items') as $idx => $item) {
            try {
                $this->drugService->stockIn((int) $item['drug_id'], (int) $item['quantity'], $item['remark'] ?? null, auth()->id());
                $results[] = ['index' => $idx, 'drug_id' => (int) $item['drug_id'], 'ok' => true];
                $ok++;
            } catch (BusinessException $e) {
                $results[] = ['index' => $idx, 'drug_id' => (int) ($item['drug_id'] ?? 0), 'ok' => false, 'error' => $e->getMessage()];
            } catch (\Throwable $e) {
                Log::error('批量入库单项失败', ['index' => $idx, 'item' => $item, 'error' => $e->getMessage()]);
                $results[] = ['index' => $idx, 'drug_id' => (int) ($item['drug_id'] ?? 0), 'ok' => false, 'error' => '系统异常'];
            }
        }
        $fail = count($results) - $ok;

        return Result::success(
            msg: $fail === 0 ? '批量入库完成' : "批量入库完成：成功 {$ok} 项，失败 {$fail} 项",
            data: ['total' => count($results), 'success' => $ok, 'failed' => $fail, 'results' => $results]
        );
    }
}
