<?php

namespace App\Http\Services\GYZ;

use App\Models\AiDiagnosis;
use App\Models\Appointment;
use App\Models\Drug;
use App\Models\MedicalRecord;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminDashboardService
{
    /**
     * 管理后台首页统计
     */
    public function dashboard(): array
    {
        return [
            'total_patients' => User::where('role', 'patient')->count(),
            'total_doctors' => User::where('role', 'doctor')->count(),
            'total_appointments' => Appointment::count(),
            'today_appointments' => Appointment::whereDate('appointment_date', now()->toDateString())->count(),
            'total_prescriptions' => Prescription::count(),
            'total_ai_diagnoses' => AiDiagnosis::count(),
            'low_stock_drugs' => Drug::where('stock_quantity', '<', 10)->count(),
        ];
    }

    /**
     * 全量预约数据（只读）
     */
    public function appointments(array $filters): LengthAwarePaginator
    {
        $query = Appointment::with(['patient:id,name', 'doctor:id,name'])
            ->select(['id', 'patient_id', 'doctor_id', 'appointment_date', 'status', 'created_at']);

        if (! empty($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }
        if (! empty($filters['patient_name'])) {
            $query->whereHas('patient', fn ($p) => $p->where('name', 'like', "%{$filters['patient_name']}%"));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('appointment_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('appointment_date', '<=', $filters['date_to']);
        }

        $sort = $filters['sort'] ?? 'created_at:desc';
        [$col, $dir] = explode(':', $sort);
        $query->orderBy($col, $dir);

        return $query->paginate($filters['size'] ?? 10, page: $filters['page'] ?? 1)
            ->through(fn ($a) => [
                'id' => $a->id,
                'patient_id' => $a->patient_id,
                'patient_name' => $a->patient?->name,
                'doctor_id' => $a->doctor_id,
                'doctor_name' => $a->doctor?->name,
                'appointment_date' => $a->appointment_date,
                'status' => $a->status,
                'created_at' => $a->created_at?->toIso8601String(),
            ]);
    }

    /**
     * 全量病历数据（只读）
     */
    public function medicalRecords(array $filters): LengthAwarePaginator
    {
        $query = MedicalRecord::with(['patient:id,name', 'doctor:id,name', 'appointment:id,appointment_date'])
            ->select(['id', 'appointment_id', 'patient_id', 'doctor_id', 'preliminary_diagnosis', 'created_at']);

        if (! empty($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }
        if (! empty($filters['patient_name'])) {
            $query->whereHas('patient', fn ($p) => $p->where('name', 'like', "%{$filters['patient_name']}%"));
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $sort = $filters['sort'] ?? 'created_at:desc';
        [$col, $dir] = explode(':', $sort);
        $query->orderBy($col, $dir);

        return $query->paginate($filters['size'] ?? 10, page: $filters['page'] ?? 1)
            ->through(fn ($r) => [
                'id' => $r->id,
                'patient_id' => $r->patient_id,
                'patient_name' => $r->patient?->name,
                'doctor_id' => $r->doctor_id,
                'doctor_name' => $r->doctor?->name,
                'appointment_date' => $r->appointment?->appointment_date,
                'preliminary_diagnosis' => $r->preliminary_diagnosis,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);
    }

    /**
     * 全量处方数据（只读）
     */
    public function prescriptions(array $filters): LengthAwarePaginator
    {
        $query = Prescription::with(['patient:id,name', 'doctor:id,name'])
            ->select(['id', 'appointment_id', 'patient_id', 'doctor_id', 'status', 'created_at']);

        if (! empty($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }
        if (! empty($filters['patient_name'])) {
            $query->whereHas('patient', fn ($p) => $p->where('name', 'like', "%{$filters['patient_name']}%"));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $sort = $filters['sort'] ?? 'created_at:desc';
        [$col, $dir] = explode(':', $sort);
        $query->orderBy($col, $dir);

        return $query->paginate($filters['size'] ?? 10, page: $filters['page'] ?? 1)
            ->through(fn ($p) => [
                'id' => $p->id,
                'patient_id' => $p->patient_id,
                'patient_name' => $p->patient?->name,
                'doctor_id' => $p->doctor_id,
                'doctor_name' => $p->doctor?->name,
                'status' => $p->status,
                'items_count' => \App\Models\PrescriptionItem::where('prescription_id', $p->id)->count(),
                'created_at' => $p->created_at?->toIso8601String(),
            ]);
    }

    /**
     * 全量AI诊断记录（只读）
     */
    public function aiDiagnoses(array $filters): LengthAwarePaginator
    {
        $query = AiDiagnosis::with(['patient:id,name', 'doctor:id,name'])
            ->select(['id', 'type', 'patient_id', 'doctor_id', 'risk_level', 'risk_assessment', 'created_at']);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['patient_name'])) {
            $query->whereHas('patient', fn ($p) => $p->where('name', 'like', "%{$filters['patient_name']}%"));
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $sort = $filters['sort'] ?? 'created_at:desc';
        [$col, $dir] = explode(':', $sort);
        $query->orderBy($col, $dir);

        return $query->paginate($filters['size'] ?? 10, page: $filters['page'] ?? 1)
            ->through(fn ($d) => [
                'id' => $d->id,
                'patient_id' => $d->patient_id,
                'patient_name' => $d->patient?->name,
                'doctor_name' => $d->doctor?->name,
                'type' => $d->type,
                'risk_level' => $d->type === 'text' ? $d->risk_level : $d->risk_assessment,
                'created_at' => $d->created_at?->toIso8601String(),
            ]);
    }
}
