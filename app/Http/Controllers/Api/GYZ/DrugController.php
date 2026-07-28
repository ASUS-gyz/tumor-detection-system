<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Http\Controllers\Controller;
use App\Http\Requests\GYZ\DrugCreateRequest;
use App\Http\Requests\GYZ\DrugUpdateRequest;
use App\Http\Requests\GYZ\StockInRequest;
use App\Http\Services\GYZ\DrugService;
use App\Http\Services\GYZ\StockMovementService;
use App\Support\PaginationHelper;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
