<?php

namespace App\Http\Controllers\Doctor;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\CreatePrescriptionRequest;
use App\Models\Appointment;
use App\Models\Drug;
use App\Models\DrugStock;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 医生端处方控制器
 *
 * 提供处方的开具、查看和列表功能。
 * 开具时校验库存但不扣减（患者确认取药时才扣减）。
 * 所有接口需 doctor 角色认证，仅可操作本人负责的预约和数据。
 */
class PrescriptionController extends Controller
{
    // ───────────────────── 1. 开具处方 ─────────────────────

    /**
     * 开具处方
     *
     * POST /api/doctor/prescriptions
     * 功能：为指定预约开具处方，包含多种药品明细。
     * 库存仅在开具时校验是否充足，不实际扣减（患者确认取药时才扣减）。
     * 参数：appointment_id, items[{drug_id, quantity, dosage, instructions}]
     */
    public function store(CreatePrescriptionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $doctorId = $request->user()->id;

        // 校验预约存在且属于当前医生
        $appointment = Appointment::where('doctor_id', $doctorId)
            ->find($validated['appointment_id']);

        if (! $appointment) {
            throw new BusinessException('预约不存在或无权限操作', ResponseCode::DATA_NOT_FOUND);
        }

        // 库存校验（不扣减，仅检查）
        $insufficient = [];
        foreach ($validated['items'] as $item) {
            $drug = Drug::find($item['drug_id']);
            $stock = DrugStock::where('drug_id', $item['drug_id'])->first();
            $currentQty = $stock ? $stock->quantity : 0;

            if ($currentQty < $item['quantity']) {
                $insufficient[] = [
                    'drug_name' => $drug->name ?? '未知药品',
                    'drug_id' => $item['drug_id'],
                    'need' => $item['quantity'],
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

        // 创建处方（事务）
        DB::beginTransaction();
        try {
            $prescription = Prescription::create([
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'doctor_id' => $doctorId,
                'status' => 'pending',
            ]);

            foreach ($validated['items'] as $item) {
                PrescriptionItem::create([
                    'prescription_id' => $prescription->id,
                    'drug_id' => $item['drug_id'],
                    'quantity' => $item['quantity'],
                    'dosage' => $item['dosage'],
                    'instructions' => $item['instructions'] ?? null,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new BusinessException('处方创建失败：' . $e->getMessage(), ResponseCode::BUSINESS_ERROR);
        }

        $prescription->load('items');

        return Result::success('处方开具成功', $this->formatDetail($prescription));
    }

    // ───────────────────── 2. 处方详情 ─────────────────────

    /**
     * 处方详情
     *
     * GET /api/doctor/prescriptions/{id}
     * 功能：返回指定处方的完整信息，含患者信息和药品明细。
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $doctorId = $request->user()->id;

        $prescription = Prescription::with([
                'patient:id,name,phone',
                'items.drug:id,name,specification',
            ])
            ->where('doctor_id', $doctorId)
            ->find($id);

        if (! $prescription) {
            throw new BusinessException('处方不存在或无权限查看', ResponseCode::DATA_NOT_FOUND);
        }

        return Result::success('成功', $this->formatDetail($prescription));
    }

    // ───────────────────── 3. 历史处方列表 ─────────────────────

    /**
     * 历史处方列表
     *
     * GET /api/doctor/prescriptions
     * 功能：分页返回当前医生的所有处方。
     * 参数：page, per_page（默认10，最大50）
     */
    public function index(Request $request): JsonResponse
    {
        $doctorId = $request->user()->id;
        $perPage = min((int) $request->input('per_page', 10), 50);

        $prescriptions = Prescription::with('patient:id,name,phone')
            ->withCount('items')
            ->where('doctor_id', $doctorId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $list = $prescriptions->getCollection()->map(function ($p) {
            return [
                'id' => $p->id,
                'patient_name' => $p->patient->name ?? '',
                'patient_phone' => $p->patient->phone ?? '',
                'status' => $p->status,
                'item_count' => $p->items_count,
                'created_at' => $p->created_at,
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

    // ───────────────────── 响应格式化 ─────────────────────

    private function formatDetail(Prescription $prescription): array
    {
        $prescription->loadMissing([
            'patient:id,name,phone',
            'items.drug:id,name,specification',
        ]);

        return [
            'id' => $prescription->id,
            'status' => $prescription->status,
            'patient' => [
                'id' => $prescription->patient->id ?? null,
                'name' => $prescription->patient->name ?? '',
                'phone' => $prescription->patient->phone ?? '',
            ],
            'items' => $prescription->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'drug_id' => $item->drug_id,
                    'drug_name' => $item->drug->name ?? '',
                    'specification' => $item->drug->specification ?? '',
                    'quantity' => $item->quantity,
                    'dosage' => $item->dosage,
                    'instructions' => $item->instructions,
                ];
            })->values(),
            'created_at' => $prescription->created_at,
        ];
    }
}
