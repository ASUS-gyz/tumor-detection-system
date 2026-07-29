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
    // ─── 患者端: 病历列表 ───

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min((int) $request->input('per_page', 10), 50);

        if ($user->role === 'patient') {
            $records = MedicalRecord::with(['doctor:id,name,title', 'appointment:id,appointment_date'])
                ->where('patient_id', $user->id)
                ->select(['id', 'appointment_id', 'doctor_id', 'symptoms', 'preliminary_diagnosis', 'created_at'])
                ->orderBy('created_at', 'desc')->paginate($perPage);
            $list = $records->getCollection()->map(fn ($r) => [
                'id' => $r->id, 'symptoms' => mb_substr($r->symptoms, 0, 50),
                'preliminary_diagnosis' => mb_substr($r->preliminary_diagnosis, 0, 50),
                'doctor_name' => $r->doctor->name ?? '', 'appointment_date' => $r->appointment->appointment_date ?? null, 'created_at' => $r->created_at,
            ]);
        } else {
            $records = MedicalRecord::with(['patient:id,name,phone', 'appointment:id,appointment_date'])
                ->where('doctor_id', $user->id)
                ->select(['id', 'appointment_id', 'patient_id', 'symptoms', 'preliminary_diagnosis', 'created_at'])
                ->orderBy('created_at', 'desc')->paginate($perPage);
            $list = $records->getCollection()->map(fn ($r) => [
                'id' => $r->id, 'patient_name' => $r->patient->name ?? '', 'patient_phone' => $r->patient->phone ?? '',
                'symptoms' => mb_substr($r->symptoms, 0, 50), 'preliminary_diagnosis' => mb_substr($r->preliminary_diagnosis, 0, 50),
                'appointment_date' => $r->appointment->appointment_date ?? null, 'created_at' => $r->created_at,
            ]);
        }
        return Result::success('成功', ['list' => $list->values(), 'page' => $records->currentPage(), 'size' => $records->perPage(), 'total' => $records->total(), 'total_pages' => $records->lastPage()]);
    }

    // ─── 患者/医生共用: 病历详情 ───

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $query = MedicalRecord::with(['patient:id,name,phone', 'doctor:id,name,title,specialty', 'appointment:id,appointment_date,appointment_time']);
        $query = $user->role === 'patient' ? $query->where('patient_id', $user->id) : $query->where('doctor_id', $user->id);
        $record = $query->find($id);
        if (! $record) { throw new BusinessException('病历记录不存在', ResponseCode::DATA_NOT_FOUND); }
        return Result::success('成功', [
            'id' => $record->id, 'symptoms' => $record->symptoms, 'imaging_findings' => $record->imaging_findings,
            'preliminary_diagnosis' => $record->preliminary_diagnosis, 'treatment_plan' => $record->treatment_plan,
            'doctor' => ['id' => $record->doctor->id ?? null, 'name' => $record->doctor->name ?? '', 'title' => $record->doctor->title ?? '', 'specialty' => $record->doctor->specialty ?? ''],
            'patient' => ['id' => $record->patient->id ?? null, 'name' => $record->patient->name ?? '', 'phone' => $record->patient->phone ?? ''],
            'appointment' => ['id' => $record->appointment->id ?? null, 'date' => $record->appointment->appointment_date ?? null, 'time' => $record->appointment->appointment_time ?? null],
            'created_at' => $record->created_at, 'updated_at' => $record->updated_at,
        ]);
    }

    // ─── 医生端: 创建病历 ───

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'symptoms' => ['required', 'string', 'min:2'], 'imaging_findings' => ['nullable', 'string'],
            'preliminary_diagnosis' => ['required', 'string', 'min:2'], 'treatment_plan' => ['required', 'string', 'min:2'],
        ]);
        $doctorId = $request->user()->id;
        $appointment = Appointment::where('doctor_id', $doctorId)->find($validated['appointment_id']);
        if (! $appointment) { throw new BusinessException('预约不存在或无权限操作', ResponseCode::DATA_NOT_FOUND); }
        if (MedicalRecord::where('appointment_id', $appointment->id)->exists()) { throw new BusinessException('该预约已创建病历', ResponseCode::DUPLICATE_SUBMIT); }
        $record = MedicalRecord::create([
            'appointment_id' => $appointment->id, 'patient_id' => $appointment->patient_id, 'doctor_id' => $doctorId,
            'symptoms' => $validated['symptoms'], 'imaging_findings' => $validated['imaging_findings'] ?? null,
            'preliminary_diagnosis' => $validated['preliminary_diagnosis'], 'treatment_plan' => $validated['treatment_plan'],
        ]);
        return Result::success('病历创建成功', $this->show($record->id, $request)->getData(true)['data']);
    }

    // ─── 医生端: 编辑病历 ───

    public function update(int $id, Request $request): JsonResponse
    {
        $record = MedicalRecord::where('doctor_id', $request->user()->id)->find($id);
        if (! $record) { throw new BusinessException('病历不存在或无权限编辑', ResponseCode::DATA_NOT_FOUND); }
        $data = array_filter($request->only(['symptoms', 'imaging_findings', 'preliminary_diagnosis', 'treatment_plan']), fn ($v) => $v !== null);
        if (empty($data)) { throw new BusinessException('至少提供一个更新字段', ResponseCode::PARAM_ERROR); }
        $record->fill($data)->save();
        return Result::success('病历更新成功');
    }

    // ─── 医生端: 多份病历对比 ───

    public function compare(Request $request): JsonResponse
    {
        $request->validate(['ids' => ['required', 'string', 'regex:/^\d+(,\d+)*$/']]);
        $ids = array_slice(array_unique(explode(',', $request->input('ids'))), 0, 5);
        $records = MedicalRecord::with(['patient:id,name', 'appointment:id,appointment_date'])->where('doctor_id', $request->user()->id)->whereIn('id', $ids)->get();
        if ($records->isEmpty()) { throw new BusinessException('未找到指定的病历', ResponseCode::DATA_NOT_FOUND); }
        return Result::success('成功', ['records' => $records->map(fn ($r) => [
            'id' => $r->id, 'patient_name' => $r->patient->name ?? '', 'appointment_date' => $r->appointment->appointment_date ?? null,
            'symptoms' => $r->symptoms, 'imaging_findings' => $r->imaging_findings, 'preliminary_diagnosis' => $r->preliminary_diagnosis, 'treatment_plan' => $r->treatment_plan, 'created_at' => $r->created_at,
        ])->values(), 'count' => $records->count()]);
    }
}
