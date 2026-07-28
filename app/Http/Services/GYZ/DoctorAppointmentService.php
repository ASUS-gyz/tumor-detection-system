<?php

namespace App\Http\Services\GYZ;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\Appointment;
use App\Models\Drug;
use App\Models\MedicalRecord;
use App\Models\Prescription;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class DoctorAppointmentService
{
    /**
     * 医生工作台统计
     */
    public function dashboard(int $doctorId): array
    {
        $today = now()->toDateString();

        return [
            'today_appointments' => Appointment::where('doctor_id', $doctorId)->where('appointment_date', $today)->count(),
            'pending_count' => Appointment::where('doctor_id', $doctorId)->where('appointment_date', $today)->where('status', 'pending')->count(),
            'in_progress_count' => Appointment::where('doctor_id', $doctorId)->where('appointment_date', $today)->where('status', 'in_progress')->count(),
            'completed_today' => Appointment::where('doctor_id', $doctorId)->where('appointment_date', $today)->where('status', 'completed')->count(),
            'total_drugs' => Drug::count(),
            'low_stock_drugs' => Drug::where('stock_quantity', '<', 10)->count(),
        ];
    }

    /**
     * 预约列表（默认今日）
     */
    public function appointmentList(int $doctorId, array $filters): LengthAwarePaginator
    {
        $query = Appointment::with('patient:id,name,phone')
            ->where('doctor_id', $doctorId)
            ->select(['id', 'patient_id', 'doctor_id', 'appointment_date', 'appointment_time', 'status', 'created_at']);

        if (! empty($filters['date'])) {
            $query->where('appointment_date', $filters['date']);
        } else {
            $query->where('appointment_date', now()->toDateString());
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['patient_name'])) {
            $query->whereHas('patient', fn ($p) => $p->where('name', 'like', "%{$filters['patient_name']}%"));
        }

        return $query->orderBy('appointment_time')
            ->paginate($filters['size'] ?? 20, page: $filters['page'] ?? 1)
            ->through(fn ($a) => [
                'id' => $a->id,
                'patient_id' => $a->patient_id,
                'patient_name' => $a->patient?->name,
                'patient_phone' => $a->patient?->phone,
                'appointment_date' => $a->appointment_date,
                'appointment_time' => $a->appointment_time,
                'status' => $a->status,
                'created_at' => $a->created_at?->toIso8601String(),
            ]);
    }

    /**
     * 预约详情
     */
    public function appointmentDetail(int $doctorId, int $appointmentId): array
    {
        $appointment = Appointment::with(['patient:id,name,phone,email', 'aiDiagnoses'])
            ->where('doctor_id', $doctorId)
            ->select(['id', 'patient_id', 'doctor_id', 'appointment_date', 'appointment_time', 'status', 'created_at', 'updated_at'])
            ->find($appointmentId);

        if (! $appointment) {
            throw new BusinessException('预约不存在或不属于当前医生', ResponseCode::DATA_NOT_FOUND);
        }

        $historyRecords = MedicalRecord::with('appointment:id,appointment_date')
            ->where('patient_id', $appointment->patient_id)
            ->where('appointment_id', '!=', $appointment->id)
            ->select(['id', 'appointment_id', 'preliminary_diagnosis'])
            ->latest()
            ->get()
            ->map(fn ($r) => [
                'id' => $r->appointment_id,
                'appointment_date' => $r->appointment?->appointment_date,
                'preliminary_diagnosis' => $r->preliminary_diagnosis,
            ]);

        return [
            'id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'patient_name' => $appointment->patient?->name,
            'patient_phone' => $appointment->patient?->phone,
            'patient_email' => $appointment->patient?->email,
            'appointment_date' => $appointment->appointment_date,
            'appointment_time' => $appointment->appointment_time,
            'status' => $appointment->status,
            'history_records' => $historyRecords->values(),
            'ai_diagnoses' => $appointment->aiDiagnoses->map(fn ($d) => [
                'id' => $d->id,
                'type' => $d->type,
                'risk_level' => $d->type === 'text' ? $d->risk_level : $d->risk_assessment,
                'created_at' => $d->created_at?->toIso8601String(),
            ])->values(),
            'created_at' => $appointment->created_at?->toIso8601String(),
            'updated_at' => $appointment->updated_at?->toIso8601String(),
        ];
    }

    /**
     * 叫号
     */
    public function call(int $doctorId, int $appointmentId): array
    {
        $appointment = $this->findOwn($doctorId, $appointmentId);
        if (! $appointment->isPending()) {
            throw new BusinessException('当前状态不可操作', ResponseCode::STATUS_NOT_ALLOWED);
        }
        $appointment->update(['status' => 'called']);

        Log::channel('business')->info('医生叫号', [
            'doctor_id' => $doctorId,
            'appointment_id' => $appointmentId,
            'status' => 'called',
        ]);

        return ['id' => $appointment->id, 'status' => 'called', 'updated_at' => $appointment->updated_at->toIso8601String()];
    }

    /**
     * 开始接诊
     */
    public function start(int $doctorId, int $appointmentId): array
    {
        $appointment = $this->findOwn($doctorId, $appointmentId);
        if (! $appointment->isCalled()) {
            throw new BusinessException('当前状态不可操作', ResponseCode::STATUS_NOT_ALLOWED);
        }
        $appointment->update(['status' => 'in_progress']);

        Log::channel('business')->info('医生开始接诊', [
            'doctor_id' => $doctorId,
            'appointment_id' => $appointmentId,
            'status' => 'in_progress',
        ]);

        return ['id' => $appointment->id, 'status' => 'in_progress', 'updated_at' => $appointment->updated_at->toIso8601String()];
    }

    /**
     * 结束接诊
     */
    public function complete(int $doctorId, int $appointmentId): array
    {
        $appointment = $this->findOwn($doctorId, $appointmentId);
        if (! $appointment->isInProgress()) {
            throw new BusinessException('当前状态不可操作', ResponseCode::STATUS_NOT_ALLOWED);
        }

        $hasRecord = MedicalRecord::where('appointment_id', $appointmentId)->exists();
        if (! $hasRecord) {
            throw new BusinessException('还未填写病历，无法结束接诊', ResponseCode::BUSINESS_ERROR);
        }

        $hasPrescription = Prescription::where('appointment_id', $appointmentId)->exists();
        if (! $hasPrescription) {
            throw new BusinessException('还未开具处方，无法结束接诊', ResponseCode::BUSINESS_ERROR);
        }

        $appointment->update(['status' => 'completed']);

        Log::channel('business')->info('医生结束接诊', [
            'doctor_id' => $doctorId,
            'appointment_id' => $appointmentId,
            'status' => 'completed',
        ]);

        return ['id' => $appointment->id, 'status' => 'completed', 'updated_at' => $appointment->updated_at->toIso8601String()];
    }

    private function findOwn(int $doctorId, int $id): Appointment
    {
        return Appointment::where('doctor_id', $doctorId)->find($id)
            ?? throw new BusinessException('预约不存在或不属于当前医生', ResponseCode::DATA_NOT_FOUND);
    }
}
