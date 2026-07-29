<?php

namespace App\Http\Controllers\Patient;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\AppointmentListRequest;
use App\Http\Requests\Patient\CreateAppointmentRequest;
use App\Models\Appointment;
use App\Models\User;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 患者端控制器
 *
 * 负责患者首页统计、医生查询、预约管理（CRUD）。
 * 所有接口需 patient 角色认证。
 */
class PatientController extends Controller
{
    // ───────────────────── 1. 患者首页统计 ─────────────────────

    /**
     * 患者首页统计
     *
     * GET /api/patient/dashboard
     * 返回：待就诊数、已完成数、AI 诊断数、最近一次预约
     */
    public function dashboard(Request $request): JsonResponse
    {
        $patientId = $request->user()->id;

        $pendingCount = Appointment::where('patient_id', $patientId)
            ->whereIn('status', ['pending', 'called', 'in_progress'])
            ->count();

        $completedCount = Appointment::where('patient_id', $patientId)
            ->where('status', 'completed')
            ->count();

        $aiDiagnosisCount = \App\Models\AIDiagnosis::where('patient_id', $patientId)
            ->where('type', 'text')
            ->count();

        $nextAppointment = Appointment::with('doctor:id,name,title,department')
            ->where('patient_id', $patientId)
            ->whereIn('status', ['pending', 'called'])
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->first();

        return Result::success('成功', [
            'pending_count' => $pendingCount,
            'completed_count' => $completedCount,
            'ai_diagnosis_count' => $aiDiagnosisCount,
            'next_appointment' => $nextAppointment ? $this->formatAppointmentSimple($nextAppointment) : null,
        ]);
    }

    // ───────────────────── 2. 医生列表 ─────────────────────

    /**
     * 医生列表
     *
     * GET /api/patient/doctors
     * 参数：keyword（搜索）、page、per_page
     * 返回：分页的 active 医生列表
     */
    public function doctors(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 50);
        $keyword = $request->input('keyword');

        $query = User::where('role', 'doctor')->where('status', 'active');

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('title', 'like', "%{$keyword}%")
                  ->orWhere('specialty', 'like', "%{$keyword}%")
                  ->orWhere('department', 'like', "%{$keyword}%");
            });
        }

        $doctors = $query->select([
                'id', 'name', 'title', 'specialty', 'department',
                'introduction', 'experience_years', 'avatar_url',
            ])
            ->orderBy('id')
            ->paginate($perPage);

        return Result::success('成功', [
            'list' => $doctors->items(),
            'page' => $doctors->currentPage(),
            'size' => $doctors->perPage(),
            'total' => $doctors->total(),
            'total_pages' => $doctors->lastPage(),
        ]);
    }

    // ───────────────────── 3. 医生详情 ─────────────────────

    /**
     * 医生详情
     *
     * GET /api/patient/doctors/{id}
     * 返回：医生完整信息
     */
    public function doctorDetail(int $id): JsonResponse
    {
        $doctor = User::where('role', 'doctor')
            ->where('status', 'active')
            ->select([
                'id', 'name', 'title', 'specialty', 'department',
                'introduction', 'experience_years', 'avatar_url', 'phone',
            ])
            ->find($id);

        if (! $doctor) {
            throw new BusinessException('医生不存在或已停诊', ResponseCode::DATA_NOT_FOUND);
        }

        return Result::success('成功', $doctor);
    }

    // ───────────────────── 4. 创建预约 ─────────────────────

    /**
     * 创建预约
     *
     * POST /api/patient/appointments
     * 参数：doctor_id, appointment_date, appointment_time
     * 约束：同一患者只能有一个进行中预约；日期不能早于今天
     */
    public function store(CreateAppointmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $patientId = $request->user()->id;

        // 冲突校验：同一患者只能有一个进行中的预约
        $hasActive = Appointment::where('patient_id', $patientId)
            ->whereIn('status', ['pending', 'called', 'in_progress'])
            ->exists();

        if ($hasActive) {
            throw new BusinessException('您已有一个进行中的预约，请先取消后再预约', ResponseCode::DUPLICATE_SUBMIT);
        }

        $appointment = Appointment::create([
            'patient_id' => $patientId,
            'doctor_id' => $validated['doctor_id'],
            'appointment_date' => $validated['appointment_date'],
            'appointment_time' => $validated['appointment_time'],
            'status' => 'pending',
        ]);

        $appointment->load('doctor:id,name,title,department');

        return Result::success('预约成功', $this->formatAppointment($appointment));
    }

    // ───────────────────── 5. 我的预约列表 ─────────────────────

    /**
     * 我的预约列表
     *
     * GET /api/patient/appointments
     * 参数：page, per_page, status, date
     * 返回：分页的本人预约列表（含医生信息）
     */
    public function index(AppointmentListRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $patientId = $request->user()->id;
        $perPage = min((int) ($validated['per_page'] ?? 10), 50);

        $query = Appointment::with('doctor:id,name,title')
            ->where('patient_id', $patientId);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['date'])) {
            $query->whereDate('appointment_date', $validated['date']);
        }

        $appointments = $query->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->paginate($perPage);

        $list = $appointments->getCollection()->map(fn ($a) => $this->formatAppointment($a));

        return Result::success('成功', [
            'list' => $list->values(),
            'page' => $appointments->currentPage(),
            'size' => $appointments->perPage(),
            'total' => $appointments->total(),
            'total_pages' => $appointments->lastPage(),
        ]);
    }

    // ───────────────────── 6. 预约详情 ─────────────────────

    /**
     * 预约详情
     *
     * GET /api/patient/appointments/{id}
     * 返回：预约信息 + 医生 + 病历 + 处方 + AI 诊断
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $patientId = $request->user()->id;

        $appointment = Appointment::with([
                'doctor:id,name,title,specialty,department',
                'medicalRecord',
                'prescription.items',
                'aiDiagnosis',
            ])
            ->where('patient_id', $patientId)
            ->find($id);

        if (! $appointment) {
            throw new BusinessException('预约记录不存在', ResponseCode::DATA_NOT_FOUND);
        }

        return Result::success('成功', $this->formatAppointmentDetail($appointment));
    }

    // ───────────────────── 7. 取消预约 ─────────────────────

    /**
     * 取消预约
     *
     * DELETE /api/patient/appointments/{id}
     * 约束：仅 pending 状态可取消，只能操作自己的预约
     */
    public function cancel(int $id, Request $request): JsonResponse
    {
        $patientId = $request->user()->id;

        $appointment = Appointment::where('patient_id', $patientId)->find($id);

        if (! $appointment) {
            throw new BusinessException('预约记录不存在', ResponseCode::DATA_NOT_FOUND);
        }

        if (! $appointment->canCancel()) {
            throw new BusinessException(
                $appointment->status === 'cancelled'
                    ? '该预约已取消'
                    : '当前状态不可取消，仅待接诊状态可取消',
                ResponseCode::STATUS_NOT_ALLOWED
            );
        }

        $appointment->update(['status' => 'cancelled']);

        return Result::success('预约已取消');
    }

    // ───────────────────── 响应格式化 ─────────────────────

    /** 预约列表项 */
    private function formatAppointment(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'appointment_date' => $appointment->appointment_date,
            'appointment_time' => $appointment->appointment_time,
            'status' => $appointment->status,
            'doctor' => $appointment->doctor ? [
                'id' => $appointment->doctor->id,
                'name' => $appointment->doctor->name,
                'title' => $appointment->doctor->title,
                'department' => $appointment->doctor->department,
            ] : null,
            'created_at' => $appointment->created_at,
        ];
    }

    /** 预约详情 */
    private function formatAppointmentDetail(Appointment $appointment): array
    {
        $data = $this->formatAppointment($appointment);

        // 医生完整信息
        $data['doctor'] = $appointment->doctor ? [
            'id' => $appointment->doctor->id,
            'name' => $appointment->doctor->name,
            'title' => $appointment->doctor->title,
            'specialty' => $appointment->doctor->specialty,
            'department' => $appointment->doctor->department,
        ] : null;

        // 病历
        $data['medical_record'] = $appointment->medicalRecord ? [
            'id' => $appointment->medicalRecord->id,
            'symptoms' => $appointment->medicalRecord->symptoms,
            'imaging_findings' => $appointment->medicalRecord->imaging_findings,
            'preliminary_diagnosis' => $appointment->medicalRecord->preliminary_diagnosis,
            'treatment_plan' => $appointment->medicalRecord->treatment_plan,
            'created_at' => $appointment->medicalRecord->created_at,
        ] : null;

        // 处方
        $data['prescription'] = null;
        if ($appointment->prescription) {
            $data['prescription'] = [
                'id' => $appointment->prescription->id,
                'status' => $appointment->prescription->status,
                'items' => $appointment->prescription->items->map(fn ($item) => [
                    'id' => $item->id,
                    'drug_id' => $item->drug_id,
                    'quantity' => $item->quantity,
                    'dosage' => $item->dosage,
                    'instructions' => $item->instructions,
                ])->values(),
                'created_at' => $appointment->prescription->created_at,
            ];
        }

        // AI 诊断（文字诊断）
        $data['ai_diagnosis'] = null;
        if ($appointment->aiDiagnosis) {
            $d = $appointment->aiDiagnosis;
            $data['ai_diagnosis'] = [
                'id' => $d->id,
                'type' => $d->type,
                'symptom_description' => $d->symptom_description,
                'analysis' => $d->analysis,
                'risk_level' => $d->risk_level,
                'risk_warning' => $d->risk_warning,
                'advice' => $d->advice,
                'possible_conditions' => $d->possible_conditions,
                'created_at' => $d->created_at,
            ];
        }

        return $data;
    }

    /** 仪表盘简单预约 */
    private function formatAppointmentSimple(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'appointment_date' => $appointment->appointment_date,
            'appointment_time' => $appointment->appointment_time,
            'status' => $appointment->status,
            'doctor_name' => $appointment->doctor->name ?? '',
            'doctor_title' => $appointment->doctor->title ?? '',
            'department' => $appointment->doctor->department ?? '',
        ];
    }

    // ───────────────────── 8. 可预约时段 ─────────────────────

    /**
     * 获取医生可预约时段
     *
     * GET /api/patient/appointments/available-slots
     * 参数：doctor_id（必填）, date（必填）
     * 返回：该医生在指定日期已被预约的时段列表
     */
    public function availableSlots(Request $request): JsonResponse
    {
        $request->validate([
            'doctor_id' => ['required', 'integer', 'exists:users,id,role,doctor,status,active'],
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $allSlots = ['08:30', '09:15', '10:00', '10:45', '13:30', '14:15', '15:00', '15:45'];
        $booked = Appointment::where('doctor_id', $request->input('doctor_id'))
            ->where('appointment_date', $request->input('date'))
            ->whereIn('status', ['pending', 'called', 'in_progress'])
            ->pluck('appointment_time')->toArray();
        return Result::success('成功', [
            'date' => $request->input('date'),
            'all_slots' => $allSlots,
            'booked_slots' => $booked,
            'available_slots' => array_values(array_diff($allSlots, $booked)),
        ]);
    }

    // ───────────────────── 9. 就诊评价 ─────────────────────

    public function review(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['nullable', 'string', 'max:500'],
        ]);
        $appointment = Appointment::where('patient_id', $request->user()->id)->find($id);
        if (! $appointment) { throw new BusinessException('预约记录不存在', ResponseCode::DATA_NOT_FOUND); }
        if ($appointment->status !== 'completed') { throw new BusinessException('仅可评价已完成的就诊', ResponseCode::STATUS_NOT_ALLOWED); }
        return Result::success('评价提交成功', [
            'appointment_id' => $appointment->id,
            'rating' => (int) $request->input('rating'),
            'content' => $request->input('content'),
        ]);
    }
}
