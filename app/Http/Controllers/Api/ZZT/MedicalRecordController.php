<?php

namespace App\Http\Controllers\Api\ZZT;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $u = $request->user(); $pp = min((int) $request->input('per_page', 10), 50);
        if ($u->role === 'patient') {
            $p = MedicalRecord::with('doctor:id,name,title', 'appointment:id,appointment_date')->where('patient_id', $u->id)->select('id', 'appointment_id', 'doctor_id', 'symptoms', 'preliminary_diagnosis', 'created_at')->orderByDesc('created_at')->paginate($pp);
            $list = $p->getCollection()->map(fn($r) => ['id' => $r->id, 'symptoms' => mb_substr($r->symptoms, 0, 50), 'preliminary_diagnosis' => mb_substr($r->preliminary_diagnosis, 0, 50), 'doctor_name' => $r->doctor->name ?? '', 'appointment_date' => $r->appointment->appointment_date ?? null, 'created_at' => $r->created_at]);
        } else {
            $p = MedicalRecord::with('patient:id,name,phone', 'appointment:id,appointment_date')->where('doctor_id', $u->id)->select('id', 'appointment_id', 'patient_id', 'symptoms', 'preliminary_diagnosis', 'created_at')->orderByDesc('created_at')->paginate($pp);
            $list = $p->getCollection()->map(fn($r) => ['id' => $r->id, 'patient_name' => $r->patient->name ?? '', 'patient_phone' => $r->patient->phone ?? '', 'symptoms' => mb_substr($r->symptoms, 0, 50), 'preliminary_diagnosis' => mb_substr($r->preliminary_diagnosis, 0, 50), 'appointment_date' => $r->appointment->appointment_date ?? null, 'created_at' => $r->created_at]);
        }
        return Result::success('成功', ['list' => $list->values(), 'page' => $p->currentPage(), 'size' => $p->perPage(), 'total' => $p->total(), 'total_pages' => $p->lastPage()]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $u = $request->user();
        $q = MedicalRecord::with('patient:id,name,phone', 'doctor:id,name,title,specialty', 'appointment:id,appointment_date,appointment_time');
        $r = ($u->role === 'patient' ? $q->where('patient_id', $u->id) : $q->where('doctor_id', $u->id))->find($id);
        if (! $r) throw new BusinessException('病历记录不存在', ResponseCode::DATA_NOT_FOUND);
        return Result::success('成功', ['id' => $r->id, 'symptoms' => $r->symptoms, 'imaging_findings' => $r->imaging_findings, 'preliminary_diagnosis' => $r->preliminary_diagnosis, 'treatment_plan' => $r->treatment_plan, 'doctor' => ['id' => $r->doctor->id ?? null, 'name' => $r->doctor->name ?? '', 'title' => $r->doctor->title ?? '', 'specialty' => $r->doctor->specialty ?? ''], 'patient' => ['id' => $r->patient->id ?? null, 'name' => $r->patient->name ?? '', 'phone' => $r->patient->phone ?? ''], 'appointment' => ['id' => $r->appointment->id ?? null, 'date' => $r->appointment->appointment_date?->format('Y-m-d'), 'time' => $r->appointment->appointment_time ?? null], 'created_at' => $r->created_at?->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'), 'updated_at' => $r->updated_at?->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s')]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate(['appointment_id' => 'required|integer|exists:appointments,id', 'symptoms' => 'required|string|min:2', 'imaging_findings' => 'nullable|string', 'preliminary_diagnosis' => 'required|string|min:2', 'treatment_plan' => 'required|string|min:2']);
        $did = $request->user()->id;
        $a = Appointment::where('doctor_id', $did)->find($v['appointment_id']);
        if (! $a) throw new BusinessException('预约不存在或无权限', ResponseCode::DATA_NOT_FOUND);
        if (MedicalRecord::where('appointment_id', $a->id)->exists()) throw new BusinessException('该预约已创建病历', ResponseCode::DUPLICATE_SUBMIT);
        $r = MedicalRecord::create(['appointment_id' => $a->id, 'patient_id' => $a->patient_id, 'doctor_id' => $did, 'symptoms' => $v['symptoms'], 'imaging_findings' => $v['imaging_findings'] ?? null, 'preliminary_diagnosis' => $v['preliminary_diagnosis'], 'treatment_plan' => $v['treatment_plan']]);
        return Result::success('病历创建成功', [
            'id' => $r->id,
            'symptoms' => $r->symptoms,
            'imaging_findings' => $r->imaging_findings,
            'preliminary_diagnosis' => $r->preliminary_diagnosis,
            'treatment_plan' => $r->treatment_plan,
            'patient' => ['id' => $a->patient_id, 'name' => $a->patient->name ?? '', 'phone' => $a->patient->phone ?? ''],
            'appointment' => ['id' => $a->id, 'date' => $a->appointment_date?->format('Y-m-d'), 'time' => $a->appointment_time],
            'created_at' => $r->created_at->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
            'updated_at' => $r->updated_at->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
        ]);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $r = MedicalRecord::where('doctor_id', $request->user()->id)->find($id);
        if (! $r) throw new BusinessException('病历不存在或无权限', ResponseCode::DATA_NOT_FOUND);
        $d = array_filter($request->only(['symptoms', 'imaging_findings', 'preliminary_diagnosis', 'treatment_plan']), fn($v) => $v !== null);
        if (empty($d)) throw new BusinessException('至少提供一个更新字段', ResponseCode::PARAM_ERROR);
        $r->fill($d)->save();
        return Result::success('病历更新成功');
    }

    public function compare(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|string|regex:/^\d+(,\d+)*$/'], ['ids.required' => '请选择要对比的病历ID']);
        $ids = array_slice(array_unique(explode(',', $request->input('ids'))), 0, 5);
        $rs = MedicalRecord::with('patient:id,name', 'appointment:id,appointment_date')->where('doctor_id', $request->user()->id)->whereIn('id', $ids)->get();
        if ($rs->isEmpty()) throw new BusinessException('未找到病历', ResponseCode::DATA_NOT_FOUND);
        return Result::success('成功', ['records' => $rs->map(fn($r) => ['id' => $r->id, 'patient_name' => $r->patient->name ?? '', 'appointment_date' => $r->appointment->appointment_date ?? null, 'symptoms' => $r->symptoms, 'imaging_findings' => $r->imaging_findings, 'preliminary_diagnosis' => $r->preliminary_diagnosis, 'treatment_plan' => $r->treatment_plan, 'created_at' => $r->created_at])->values(), 'count' => $rs->count()]);
    }
}
