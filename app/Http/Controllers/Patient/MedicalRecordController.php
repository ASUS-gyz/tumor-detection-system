<?php

namespace App\Http\Controllers\Patient;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 患者端病历控制器
 *
 * 提供患者查看本人病历列表和详情的功能。
 * 所有接口需 patient 角色认证，仅可查看本人数据。
 */
class MedicalRecordController extends Controller
{
    // ───────────────────── 1. 我的病历列表 ─────────────────────

    /**
     * 我的病历列表
     *
     * GET /api/patient/medical-records
     * 功能：分页返回当前患者的病历，含医生姓名和预约日期。
     * 参数：page, per_page（默认10，最大50）
     */
    public function index(Request $request): JsonResponse
    {
        $patientId = $request->user()->id;
        $perPage = min((int) $request->input('per_page', 10), 50);

        $records = MedicalRecord::with([
                'doctor:id,name,title',
                'appointment:id,appointment_date',
            ])
            ->where('patient_id', $patientId)
            ->select([
                'id', 'appointment_id', 'doctor_id',
                'symptoms', 'preliminary_diagnosis', 'created_at',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $list = $records->getCollection()->map(function ($r) {
            return [
                'id' => $r->id,
                'symptoms' => mb_substr($r->symptoms, 0, 50),
                'preliminary_diagnosis' => mb_substr($r->preliminary_diagnosis, 0, 50),
                'doctor_name' => $r->doctor->name ?? '',
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

    // ───────────────────── 2. 病历详情 ─────────────────────

    /**
     * 病历详情
     *
     * GET /api/patient/medical-records/{id}
     * 功能：返回指定病历的完整信息，含医生信息和预约时间。
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $patientId = $request->user()->id;

        $record = MedicalRecord::with([
                'doctor:id,name,title,specialty',
                'appointment:id,appointment_date,appointment_time',
            ])
            ->where('patient_id', $patientId)
            ->find($id);

        if (! $record) {
            throw new BusinessException('病历记录不存在', ResponseCode::DATA_NOT_FOUND);
        }

        return Result::success('成功', [
            'id' => $record->id,
            'symptoms' => $record->symptoms,
            'imaging_findings' => $record->imaging_findings,
            'preliminary_diagnosis' => $record->preliminary_diagnosis,
            'treatment_plan' => $record->treatment_plan,
            'doctor' => [
                'id' => $record->doctor->id ?? null,
                'name' => $record->doctor->name ?? '',
                'title' => $record->doctor->title ?? '',
                'specialty' => $record->doctor->specialty ?? '',
            ],
            'appointment' => [
                'date' => $record->appointment->appointment_date ?? null,
                'time' => $record->appointment->appointment_time ?? null,
            ],
            'created_at' => $record->created_at,
        ]);
    }
}
