<?php

namespace App\Http\Controllers\Api\ZZT;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Drug;
use App\Models\DrugStock;
use App\Models\DrugStockChange;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrescriptionController extends Controller
{
    // ─── 患者/医生共用: 处方列表 ───

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min((int) $request->input('per_page', 10), 50);
        $query = Prescription::with('doctor:id,name,title')->withCount('items');

        if ($user->role === 'patient') {
            $query->where('patient_id', $user->id);
        } else {
            $query->with('patient:id,name,phone')->where('doctor_id', $user->id);
        }
        if ($status = $request->input('status')) { $query->where('status', $status); }
        $prescriptions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        if ($user->role === 'patient') {
            $list = $prescriptions->getCollection()->map(fn ($p) => ['id' => $p->id, 'status' => $p->status, 'doctor_name' => $p->doctor->name ?? '', 'created_at' => $p->created_at, 'item_count' => $p->items_count]);
        } else {
            $list = $prescriptions->getCollection()->map(fn ($p) => ['id' => $p->id, 'patient_name' => $p->patient->name ?? '', 'patient_phone' => $p->patient->phone ?? '', 'status' => $p->status, 'item_count' => $p->items_count, 'created_at' => $p->created_at]);
        }
        return Result::success('成功', ['list' => $list->values(), 'page' => $prescriptions->currentPage(), 'size' => $prescriptions->perPage(), 'total' => $prescriptions->total(), 'total_pages' => $prescriptions->lastPage()]);
    }

    // ─── 患者/医生共用: 处方详情 ───

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Prescription::with(['doctor:id,name,title', 'patient:id,name,phone', 'items.drug:id,name,specification']);
        $query = $user->role === 'patient' ? $query->where('patient_id', $user->id) : $query->where('doctor_id', $user->id);
        $prescription = $query->find($id);
        if (! $prescription) { throw new BusinessException('处方记录不存在', ResponseCode::DATA_NOT_FOUND); }
        return Result::success('成功', [
            'id' => $prescription->id, 'status' => $prescription->status,
            'doctor' => ['id' => $prescription->doctor->id ?? null, 'name' => $prescription->doctor->name ?? '', 'title' => $prescription->doctor->title ?? ''],
            'patient' => ['id' => $prescription->patient->id ?? null, 'name' => $prescription->patient->name ?? '', 'phone' => $prescription->patient->phone ?? ''],
            'items' => $prescription->items->map(fn ($i) => ['id' => $i->id, 'drug_id' => $i->drug_id, 'drug_name' => $i->drug->name ?? '', 'specification' => $i->drug->specification ?? '', 'quantity' => $i->quantity, 'dosage' => $i->dosage, 'instructions' => $i->instructions])->values(),
            'created_at' => $prescription->created_at,
        ]);
    }

    // ─── 患者端: 确认取药 ───

    public function confirm(int $id, Request $request): JsonResponse
    {
        $prescription = Prescription::with('items.drug')->where('patient_id', $request->user()->id)->find($id);
        if (! $prescription) { throw new BusinessException('处方记录不存在', ResponseCode::DATA_NOT_FOUND); }
        if ($prescription->status !== 'pending') { throw new BusinessException($prescription->status === 'dispensed' ? '该处方已取药' : '当前状态不可取药', ResponseCode::STATUS_NOT_ALLOWED); }
        $insufficient = [];
        foreach ($prescription->items as $item) { $s = DrugStock::where('drug_id', $item->drug_id)->first(); if (($s ? $s->quantity : 0) < $item->quantity) { $insufficient[] = ['drug_name' => $item->drug->name ?? '', 'need' => $item->quantity, 'have' => $s ? $s->quantity : 0]; } }
        if ($insufficient) { return Result::error(ResponseCode::STOCK_NOT_ENOUGH, '库存不足：' . implode('、', array_column($insufficient, 'drug_name')), ['detail' => $insufficient]); }
        DB::beginTransaction();
        try {
            foreach ($prescription->items as $item) {
                $stock = DrugStock::where('drug_id', $item->drug_id)->lockForUpdate()->first();
                if (! $stock || $stock->quantity < $item->quantity) { throw new \Exception("库存不足"); }
                $b = $stock->quantity; $stock->quantity -= $item->quantity; $stock->save();
                DrugStockChange::create(['drug_id' => $item->drug_id, 'type' => 'out', 'quantity' => $item->quantity, 'before_quantity' => $b, 'after_quantity' => $stock->quantity, 'reason' => '取药 - 处方#' . $prescription->id, 'related_id' => $prescription->id, 'related_type' => 'prescription']);
            }
            $prescription->status = 'dispensed'; $prescription->save();
            DB::commit();
        } catch (\Throwable $e) { DB::rollBack(); throw new BusinessException('取药失败', ResponseCode::BUSINESS_ERROR); }
        return Result::success('取药成功');
    }

    // ─── 患者端: 续方 ───

    public function refill(int $id, Request $request): JsonResponse
    {
        $p = Prescription::with('items.drug')->where('patient_id', $request->user()->id)->find($id);
        if (! $p) { throw new BusinessException('处方不存在', ResponseCode::DATA_NOT_FOUND); }
        if ($p->status !== 'dispensed') { throw new BusinessException('仅可续方已取药的处方', ResponseCode::STATUS_NOT_ALLOWED); }
        return Result::success('续方申请已提交', ['original_prescription_id' => $p->id, 'items' => $p->items->map(fn ($i) => ['drug_name' => $i->drug->name ?? '', 'dosage' => $i->dosage, 'quantity' => $i->quantity])]);
    }

    // ─── 患者端: 用药提醒 ───

    public function medicationReminders(Request $request): JsonResponse
    {
        $ps = Prescription::with('items.drug', 'doctor:id,name')->where('patient_id', $request->user()->id)->where('status', 'pending')->get();
        $r = []; foreach ($ps as $p) { foreach ($p->items as $i) { $r[] = ['prescription_id' => $p->id, 'drug_name' => $i->drug->name ?? '', 'dosage' => $i->dosage, 'instructions' => $i->instructions, 'doctor_name' => $p->doctor->name ?? '', 'created_at' => $p->created_at]; } }
        return Result::success('成功', ['reminders' => $r, 'total' => count($r)]);
    }

    // ─── 医生端: 开具处方 ───

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.drug_id' => ['required', 'integer', 'exists:drugs,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.dosage' => ['required', 'string'],
            'items.*.instructions' => ['nullable', 'string'],
        ]);
        $doctorId = $request->user()->id;
        $appointment = Appointment::where('doctor_id', $doctorId)->find($validated['appointment_id']);
        if (! $appointment) { throw new BusinessException('预约不存在或无权限', ResponseCode::DATA_NOT_FOUND); }
        $insufficient = [];
        foreach ($validated['items'] as $item) { $s = DrugStock::where('drug_id', $item['drug_id'])->first(); if (($s ? $s->quantity : 0) < $item['quantity']) { $insufficient[] = ['drug_name' => Drug::find($item['drug_id'])->name ?? '', 'need' => $item['quantity'], 'have' => $s ? $s->quantity : 0]; } }
        if ($insufficient) { return Result::error(ResponseCode::STOCK_NOT_ENOUGH, '库存不足：' . implode('、', array_column($insufficient, 'drug_name')), ['detail' => $insufficient]); }
        DB::beginTransaction();
        try {
            $rx = Prescription::create(['appointment_id' => $appointment->id, 'patient_id' => $appointment->patient_id, 'doctor_id' => $doctorId, 'status' => 'pending']);
            foreach ($validated['items'] as $item) { PrescriptionItem::create(['prescription_id' => $rx->id, 'drug_id' => $item['drug_id'], 'quantity' => $item['quantity'], 'dosage' => $item['dosage'], 'instructions' => $item['instructions'] ?? null]); }
            DB::commit();
        } catch (\Throwable $e) { DB::rollBack(); throw new BusinessException('处方创建失败', ResponseCode::BUSINESS_ERROR); }
        $rx->load('items.drug:id,name,specification');
        return Result::success('处方开具成功', $this->show($rx->id, $request)->getData(true)['data']);
    }
}
