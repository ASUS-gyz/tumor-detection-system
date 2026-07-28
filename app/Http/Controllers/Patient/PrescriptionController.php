<?php

namespace App\Http\Controllers\Patient;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\DrugStock;
use App\Models\DrugStockChange;
use App\Models\Prescription;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 患者端处方控制器
 *
 * 提供患者查看本人处方列表、详情，以及确认取药（扣减库存）的功能。
 * 所有接口需 patient 角色认证，仅可操作本人数据。
 */
class PrescriptionController extends Controller
{
    // ───────────────────── 1. 我的处方列表 ─────────────────────

    /**
     * 我的处方列表
     *
     * GET /api/patient/prescriptions
     * 功能：分页返回当前患者的处方，支持按状态筛选。
     * 参数：page, per_page, status（pending/dispensed）
     */
    public function index(Request $request): JsonResponse
    {
        $patientId = $request->user()->id;
        $perPage = min((int) $request->input('per_page', 10), 50);

        $query = Prescription::with('doctor:id,name,title')
            ->withCount('items')
            ->where('patient_id', $patientId);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $prescriptions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $list = $prescriptions->getCollection()->map(function ($p) {
            return [
                'id' => $p->id,
                'status' => $p->status,
                'doctor_name' => $p->doctor->name ?? '',
                'created_at' => $p->created_at,
                'item_count' => $p->items_count,
            ];
        });

        return Result::success('成功', [
            'list' => $list->values(),
            'page' => $prescriptions->currentPage(),
            'size' => $prescriptions->perPage(),
            'total' => $prescriptions->total(),
            'total_pages' => $prescriptions->lastPage(),
        ]);
    }

    // ───────────────────── 2. 处方详情 ─────────────────────

    /**
     * 处方详情
     *
     * GET /api/patient/prescriptions/{id}
     * 功能：返回处方的完整信息，包含所有药品明细。
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $patientId = $request->user()->id;

        $prescription = Prescription::with([
                'doctor:id,name,title',
                'items.drug:id,name,specification',
            ])
            ->where('patient_id', $patientId)
            ->find($id);

        if (! $prescription) {
            throw new BusinessException('处方记录不存在', ResponseCode::DATA_NOT_FOUND);
        }

        return Result::success('成功', [
            'id' => $prescription->id,
            'status' => $prescription->status,
            'doctor' => [
                'id' => $prescription->doctor->id ?? null,
                'name' => $prescription->doctor->name ?? '',
                'title' => $prescription->doctor->title ?? '',
            ],
            'items' => $prescription->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'drug_name' => $item->drug->name ?? '',
                    'specification' => $item->drug->specification ?? '',
                    'quantity' => $item->quantity,
                    'dosage' => $item->dosage,
                    'instructions' => $item->instructions,
                ];
            })->values(),
            'created_at' => $prescription->created_at,
        ]);
    }

    // ───────────────────── 3. 确认取药 ─────────────────────

    /**
     * 确认取药（含库存扣减事务）
     *
     * POST /api/patient/prescriptions/{id}/confirm
     * 功能：
     *  1. 校验处方状态为 pending
     *  2. 逐项检查库存是否充足
     *  3. 在事务中扣减库存 + 记录变动日志 + 更新处方状态
     */
    public function confirm(int $id, Request $request): JsonResponse
    {
        $patientId = $request->user()->id;

        $prescription = Prescription::with('items.drug')
            ->where('patient_id', $patientId)
            ->find($id);

        if (! $prescription) {
            throw new BusinessException('处方记录不存在', ResponseCode::DATA_NOT_FOUND);
        }

        if ($prescription->status !== 'pending') {
            throw new BusinessException(
                $prescription->status === 'dispensed' ? '该处方已取药，请勿重复操作' : '当前处方状态不可取药',
                ResponseCode::STATUS_NOT_ALLOWED
            );
        }

        if ($prescription->items->isEmpty()) {
            throw new BusinessException('处方无药品明细，无法取药', ResponseCode::BUSINESS_ERROR);
        }

        // 第一步：预检查库存（不在事务内）
        $insufficient = [];
        foreach ($prescription->items as $item) {
            $stock = DrugStock::where('drug_id', $item->drug_id)->first();
            $currentQty = $stock ? $stock->quantity : 0;

            if ($currentQty < $item->quantity) {
                $insufficient[] = [
                    'drug_name' => $item->drug->name ?? '未知药品',
                    'need' => $item->quantity,
                    'have' => $currentQty,
                ];
            }
        }

        if (! empty($insufficient)) {
            $names = implode('、', array_column($insufficient, 'drug_name'));
            return Result::error(
                ResponseCode::STOCK_NOT_ENOUGH,
                "以下药品库存不足：{$names}",
                ['detail' => $insufficient]
            );
        }

        // 第二步：事务内执行扣减
        DB::beginTransaction();
        try {
            foreach ($prescription->items as $item) {
                $stock = DrugStock::where('drug_id', $item->drug_id)->lockForUpdate()->first();

                if (! $stock || $stock->quantity < $item->quantity) {
                    throw new \Exception("药品 [{$item->drug->name}] 库存在扣减时不足");
                }

                $before = $stock->quantity;
                $stock->quantity -= $item->quantity;
                $stock->save();

                // 记录库存变动日志
                DrugStockChange::create([
                    'drug_id' => $item->drug_id,
                    'type' => 'out',
                    'quantity' => $item->quantity,
                    'before_quantity' => $before,
                    'after_quantity' => $stock->quantity,
                    'reason' => '患者取药 - 处方#' . $prescription->id,
                    'related_id' => $prescription->id,
                    'related_type' => 'prescription',
                ]);
            }

            // 更新处方状态
            $prescription->status = 'dispensed';
            $prescription->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new BusinessException(
                '取药失败：' . $e->getMessage(),
                ResponseCode::BUSINESS_ERROR
            );
        }

        return Result::success('取药成功，库存已自动扣减');
    }
}
