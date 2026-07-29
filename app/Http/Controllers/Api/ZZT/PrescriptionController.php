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
    public function index(Request $request): JsonResponse
    {
        $u = $request->user(); $pp = min((int) $request->input('per_page', 10), 50);
        $q = Prescription::with('doctor:id,name,title')->withCount('items');
        if ($u->role === 'patient') $q->where('patient_id', $u->id); else { $q->with('patient:id,name,phone')->where('doctor_id', $u->id); }
        if ($s = $request->input('status')) $q->where('status', $s);
        $p = $q->orderByDesc('created_at')->paginate($pp);
        if ($u->role === 'patient') $list = $p->getCollection()->map(fn($x) => ['id' => $x->id, 'status' => $x->status, 'doctor_name' => $x->doctor->name ?? '', 'created_at' => $x->created_at, 'item_count' => $x->items_count]);
        else $list = $p->getCollection()->map(fn($x) => ['id' => $x->id, 'patient_name' => $x->patient->name ?? '', 'patient_phone' => $x->patient->phone ?? '', 'status' => $x->status, 'item_count' => $x->items_count, 'created_at' => $x->created_at]);
        return Result::success('成功', ['list' => $list->values(), 'page' => $p->currentPage(), 'size' => $p->perPage(), 'total' => $p->total(), 'total_pages' => $p->lastPage()]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $u = $request->user(); $q = Prescription::with('doctor:id,name,title', 'patient:id,name,phone', 'items.drug:id,name,specification');
        $rx = ($u->role === 'patient' ? $q->where('patient_id', $u->id) : $q->where('doctor_id', $u->id))->find($id);
        if (! $rx) throw new BusinessException('处方记录不存在', ResponseCode::DATA_NOT_FOUND);
        return Result::success('成功', ['id' => $rx->id, 'status' => $rx->status, 'doctor' => ['id' => $rx->doctor->id ?? null, 'name' => $rx->doctor->name ?? '', 'title' => $rx->doctor->title ?? ''], 'patient' => ['id' => $rx->patient->id ?? null, 'name' => $rx->patient->name ?? '', 'phone' => $rx->patient->phone ?? ''], 'items' => $rx->items->map(fn($i) => ['id' => $i->id, 'drug_id' => $i->drug_id, 'drug_name' => $i->drug->name ?? '', 'specification' => $i->drug->specification ?? '', 'quantity' => $i->quantity, 'dosage' => $i->dosage, 'instructions' => $i->instructions])->values(), 'created_at' => $rx->created_at]);
    }

    public function confirm(int $id, Request $request): JsonResponse
    {
        $rx = Prescription::with('items.drug')->where('patient_id', $request->user()->id)->find($id);
        if (! $rx) throw new BusinessException('处方不存在', ResponseCode::DATA_NOT_FOUND);
        if ($rx->status !== 'pending') throw new BusinessException($rx->status === 'dispensed' ? '已取药' : '状态不可操作', ResponseCode::STATUS_NOT_ALLOWED);
        $ins = []; foreach ($rx->items as $i) { $s = DrugStock::where('drug_id', $i->drug_id)->first(); if (($s ? $s->quantity : 0) < $i->quantity) $ins[] = ['drug_name' => $i->drug->name ?? '', 'need' => $i->quantity, 'have' => $s ? $s->quantity : 0]; }
        if ($ins) return Result::error(ResponseCode::STOCK_NOT_ENOUGH, '库存不足：' . implode('、', array_column($ins, 'drug_name')), ['detail' => $ins]);
        DB::beginTransaction();
        try { foreach ($rx->items as $i) { $s = DrugStock::where('drug_id', $i->drug_id)->lockForUpdate()->first(); if (! $s || $s->quantity < $i->quantity) throw new \Exception("库存不足"); $b = $s->quantity; $s->quantity -= $i->quantity; $s->save(); DrugStockChange::create(['drug_id' => $i->drug_id, 'type' => 'out', 'quantity' => $i->quantity, 'before_quantity' => $b, 'after_quantity' => $s->quantity, 'reason' => '取药#'.$rx->id, 'related_id' => $rx->id, 'related_type' => 'prescription']); } $rx->status = 'dispensed'; $rx->save(); DB::commit(); } catch (\Throwable $e) { DB::rollBack(); throw new BusinessException('取药失败', ResponseCode::BUSINESS_ERROR); }
        return Result::success('取药成功');
    }

    public function refill(int $id, Request $request): JsonResponse
    { $p = Prescription::with('items.drug')->where('patient_id', $request->user()->id)->find($id); if (! $p) throw new BusinessException('处方不存在', ResponseCode::DATA_NOT_FOUND); if ($p->status !== 'dispensed') throw new BusinessException('仅可续方已取药处方', ResponseCode::STATUS_NOT_ALLOWED); return Result::success('续方申请已提交', ['original_prescription_id' => $p->id, 'items' => $p->items->map(fn($i) => ['drug_name' => $i->drug->name ?? '', 'dosage' => $i->dosage, 'quantity' => $i->quantity])]); }

    public function medicationReminders(Request $request): JsonResponse
    { $ps = Prescription::with('items.drug', 'doctor:id,name')->where('patient_id', $request->user()->id)->where('status', 'pending')->get(); $r = []; foreach ($ps as $p) foreach ($p->items as $i) $r[] = ['prescription_id' => $p->id, 'drug_name' => $i->drug->name ?? '', 'dosage' => $i->dosage, 'instructions' => $i->instructions, 'doctor_name' => $p->doctor->name ?? '', 'created_at' => $p->created_at]; return Result::success('成功', ['reminders' => $r, 'total' => count($r)]); }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate(['appointment_id' => 'required|integer|exists:appointments,id', 'items' => 'required|array|min:1', 'items.*.drug_id' => 'required|integer|exists:drugs,id', 'items.*.quantity' => 'required|integer|min:1', 'items.*.dosage' => 'required|string', 'items.*.instructions' => 'nullable|string']);
        $did = $request->user()->id; $a = Appointment::where('doctor_id', $did)->find($v['appointment_id']);
        if (! $a) throw new BusinessException('预约不存在或无权限', ResponseCode::DATA_NOT_FOUND);
        $ins = []; foreach ($v['items'] as $i) { $s = DrugStock::where('drug_id', $i['drug_id'])->first(); if (($s ? $s->quantity : 0) < $i['quantity']) $ins[] = ['drug_name' => Drug::find($i['drug_id'])->name ?? '', 'need' => $i['quantity'], 'have' => $s ? $s->quantity : 0]; }
        if ($ins) return Result::error(ResponseCode::STOCK_NOT_ENOUGH, '库存不足：' . implode('、', array_column($ins, 'drug_name')), ['detail' => $ins]);
        DB::beginTransaction();
        try { $rx = Prescription::create(['appointment_id' => $a->id, 'patient_id' => $a->patient_id, 'doctor_id' => $did, 'status' => 'pending']); foreach ($v['items'] as $i) PrescriptionItem::create(['prescription_id' => $rx->id, 'drug_id' => $i['drug_id'], 'quantity' => $i['quantity'], 'dosage' => $i['dosage'], 'instructions' => $i['instructions'] ?? null]); DB::commit(); } catch (\Throwable $e) { DB::rollBack(); throw new BusinessException('处方创建失败', ResponseCode::BUSINESS_ERROR); }
        $rx->load('items.drug:id,name,specification');
        return Result::success('处方开具成功');
    }
}
