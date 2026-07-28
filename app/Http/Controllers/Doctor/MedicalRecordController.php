<?php

namespace App\Http\Controllers\Doctor;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\CreateMedicalRecordRequest;
use App\Http\Requests\Doctor\UpdateMedicalRecordRequest;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 医生端病历控制器
 *
 * 提供病历的创建、编辑、查看和列表功能。
 * 所有接口需 doctor 角色认证，仅可操作本人负责的预约和数据。
 */
class MedicalRecordController extends Controller
{
    // ───────────────────── 1. 创建病历 ─────────────────────

    /**
     * 创建病历
     *
     * POST /api/doctor/medical-records
     * 功能：为指定预约创建肿瘤科病历，自动填充 patient_id 和 doctor_id。
     * 参数：appointment_id, symptoms, imaging_findings（可选）, preliminary_diagnosis, treatment_plan
     */
    public function store(CreateMedicalRecordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $doctorId = $request->user()->id;

        // 校验预约存在且属于当前医生
        $appointment = Appointment::where('doctor_id', $doctorId)
            ->find($validated['appointment_id']);

        if (! $appointment) {
            throw new BusinessException('预约不存在或无权限操作', ResponseCode::DATA_NOT_FOUND);
        }

        // 检查是否已创建病历
        if (MedicalRecord::where('appointment_id', $appointment->id)->exists()) {
            throw new BusinessException('该预约已创建病历，请使用编辑功能', ResponseCode::DUPLICATE_SUBMIT);
        }

        $record = MedicalRecord::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $doctorId,
            'symptoms' => $validated['symptoms'],
            'imaging_findings' => $validated['imaging_findings'] ?? null,
            'preliminary_diagnosis' => $validated['preliminary_diagnosis'],
            'treatment_plan' => $validated['treatment_plan'],
        ]);

        return Result::success('病历创建成功', $this->formatDetail($record));
    }

    // ───────────────────── 2. 编辑病历 ─────────────────────

    /**
     * 编辑病历
     *
     * PUT /api/doctor/medical-records/{id}
     * 功能：编辑自己创建的病历，传什么字段更新什么字段。
     * 参数：symptoms, imaging_findings, preliminary_diagnosis, treatment_plan（均可选）
     */
    public function update(int $id, UpdateMedicalRecordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $doctorId = $request->user()->id;

        $record = MedicalRecord::where('doctor_id', $doctorId)->find($id);

        if (! $record) {
            throw new BusinessException('病历不存在或无权限编辑', ResponseCode::DATA_NOT_FOUND);
        }

        // 仅更新传入的非 null 字段
        $updatable = ['symptoms', 'imaging_findings', 'preliminary_diagnosis', 'treatment_plan'];
        $data = array_filter(array_intersect_key($validated, array_flip($updatable)), fn ($v) => $v !== null);

        if (empty($data)) {
            throw new BusinessException('至少提供一个更新字段', ResponseCode::PARAM_ERROR);
        }

        $record->fill($data)->save();

        return Result::success('病历更新成功', $this->formatDetail($record->fresh()));
    }

    // ───────────────────── 3. 病历详情 ─────────────────────

    /**
     * 病历详情
     *
     * GET /api/doctor/medical-records/{id}
     * 功能：返回指定病历的完整信息，含患者信息和预约信息。
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $doctorId = $request->user()->id;

        $record = MedicalRecord::with([
                'patient:id,name,phone',
                'appointment:id,appointment_date,appointment_time',
            ])
            ->where('doctor_id', $doctorId)
            ->find($id);

        if (! $record) {
            throw new BusinessException('病历不存在或无权限查看', ResponseCode::DATA_NOT_FOUND);
        }

        return Result::success('成功', $this->formatDetail($record));
    }

    // ───────────────────── 4. 历史病历列表 ─────────────────────

    /**
     * 历史病历列表
     *
     * GET /api/doctor/medical-records
     * 功能：分页返回当前医生的所有病历。
     * 参数：page, per_page（默认10，最大50）
     */
    public function index(Request $request): JsonResponse
    {
        $doctorId = $request->user()->id;
        $perPage = min((int) $request->input('per_page', 10), 50);

        $records = MedicalRecord::with([
                'patient:id,name,phone',
                'appointment:id,appointment_date',
            ])
            ->where('doctor_id', $doctorId)
            ->select(['id', 'appointment_id', 'patient_id', 'symptoms', 'preliminary_diagnosis', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $list = $records->getCollection()->map(function ($r) {
            return [
                'id' => $r->id,
                'patient_name' => $r->patient->name ?? '',
                'patient_phone' => $r->patient->phone ?? '',
                'symptoms' => mb_substr($r->symptoms, 0, 50),
                'preliminary_diagnosis' => mb_substr($r->preliminary_diagnosis, 0, 50),
                'appointment_date' => $r->appointment->appointment_date ?? null,
                'created_at' => $r->created_at,
            ];
        });

        return Result::success('成功', [
            'list' => $list->values(),
            'page' => $records->currentPage(),
            'size' => $records->perPage(),
            'total' => $records->total(),
            'total_pages' => $records->lastPage(),
        ]);
    }

    // ───────────────────── 响应格式化 ─────────────────────

    private function formatDetail(MedicalRecord $record): array
    {
        $record->loadMissing(['patient:id,name,phone', 'appointment:id,appointment_date,appointment_time']);

        return [
            'id' => $record->id,
            'symptoms' => $record->symptoms,
            'imaging_findings' => $record->imaging_findings,
            'preliminary_diagnosis' => $record->preliminary_diagnosis,
            'treatment_plan' => $record->treatment_plan,
            'patient' => [
                'id' => $record->patient->id ?? null,
                'name' => $record->patient->name ?? '',
                'phone' => $record->patient->phone ?? '',
            ],
            'appointment' => [
                'id' => $record->appointment->id ?? null,
                'date' => $record->appointment->appointment_date ?? null,
                'time' => $record->appointment->appointment_time ?? null,
            ],
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,
        ];
    }

    public function compare(Request $request): JsonResponse
    {
        $request->validate(['ids' => ['required', 'string', 'regex:/^\d+(,\d+)*$/']]);
        $ids = array_slice(array_unique(explode(',', $request->input('ids'))), 0, 5);
        $records = MedicalRecord::with(['patient:id,name', 'appointment:id,appointment_date'])
            ->where('doctor_id', $request->user()->id)->whereIn('id', $ids)->get();
        if ($records->isEmpty()) { throw new BusinessException('未找到指定的病历', ResponseCode::DATA_NOT_FOUND); }
        return Result::success('成功', [
            'records' => $records->map(fn ($r) => [
                'id' => $r->id, 'patient_name' => $r->patient->name ?? '',
                'appointment_date' => $r->appointment->appointment_date ?? null,
                'symptoms' => $r->symptoms, 'imaging_findings' => $r->imaging_findings,
                'preliminary_diagnosis' => $r->preliminary_diagnosis, 'treatment_plan' => $r->treatment_plan,
                'created_at' => $r->created_at,
            ])->values(),
            'count' => $records->count(),
    // ───────────────────── 5. 多份病历对比 ─────────────────────

    /**
     * 多份病历对比
     *
     * GET /api/doctor/medical-records/compare
     * 功能：选择多份病历进行对比分析。
     * 参数：ids（必填，逗号分隔的病历ID列表，最多5份）
     */
    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'string', 'regex:/^\d+(,\d+)*$/'],
        ], [
            'ids.required' => '请选择要对比的病历',
            'ids.regex' => '病历ID格式不正确',
        ]);

        $doctorId = $request->user()->id;
        $ids = array_slice(array_unique(explode(',', $request->input('ids'))), 0, 5);

        $records = MedicalRecord::with(['patient:id,name', 'appointment:id,appointment_date'])
            ->where('doctor_id', $doctorId)
            ->whereIn('id', $ids)
            ->get();

        if ($records->isEmpty()) {
            throw new BusinessException('未找到指定的病历', ResponseCode::DATA_NOT_FOUND);
        }

        $list = $records->map(fn ($r) => [
            'id' => $r->id,
            'patient_name' => $r->patient->name ?? '',
            'appointment_date' => $r->appointment->appointment_date ?? null,
            'symptoms' => $r->symptoms,
            'imaging_findings' => $r->imaging_findings,
            'preliminary_diagnosis' => $r->preliminary_diagnosis,
            'treatment_plan' => $r->treatment_plan,
            'created_at' => $r->created_at,
        ])->values();

        return Result::success('成功', [
            'records' => $list,
            'count' => $list->count(),
        ]);
    }
}
