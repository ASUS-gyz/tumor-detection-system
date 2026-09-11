<?php

namespace App\Http\Services\GYZ;

use App\Models\AiDiagnosis;
use App\Models\Appointment;
use App\Models\Drug;
use App\Models\MedicalRecord;
use App\Models\Prescription;
use App\Http\Services\GYZ\DrugService;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminStatisticsService
{
    /**
     * 医生工作量统计
     */
    public function doctorWorkload(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Appointment::select(
                'doctor_id',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status='completed' then 1 else 0 end) as completed"),
                DB::raw("sum(case when status='cancelled' then 1 else 0 end) as cancelled")
            )
            ->groupBy('doctor_id');

        if ($dateFrom) {
            $query->whereDate('appointment_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('appointment_date', '<=', $dateTo);
        }

        return $query->with('doctor:id,name,title')
            ->get()
            ->map(fn ($r) => [
                'doctor_id' => $r->doctor_id,
                'doctor_name' => $r->doctor?->name,
                'title' => $r->doctor?->title,
                'total_appointments' => $r->total,
                'completed_appointments' => $r->completed,
                'cancelled_appointments' => (int) $r->cancelled,
                // 接诊完成率只统计实际接诊的预约（剔除已取消）
                'completion_rate' => ($r->total - $r->cancelled) > 0 ? round($r->completed / ($r->total - $r->cancelled) * 100, 1) : 0,
            ])
            ->values()
            ->toArray();
    }

    /**
     * 药品消耗统计
     */
    public function drugConsumption(?string $dateFrom = null, ?string $dateTo = null): array
    {
        // 出库统一读 stock_movements：处方发药出库已同步写入该表（PrescriptionController::confirm），
        // 不再合并 drug_stock_changes，避免同一批发药双计
        return StockMovement::with('drug:id,name,specification')
            ->where('type', 'out')
            ->select('drug_id', DB::raw('sum(quantity) as total_out'), DB::raw('count(*) as times'))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->groupBy('drug_id')
            ->get()
            ->map(fn ($r) => [
                'drug_id' => $r->drug_id, 'drug_name' => $r->drug?->name, 'specification' => $r->drug?->specification,
                'total_dispensed' => (int) $r->total_out, 'dispense_count' => (int) $r->times,
            ])
            ->sortByDesc('total_dispensed')
            ->values()
            ->toArray();
    }

    /**
     * 月度趋势统计
     */
    public function monthlyTrend(int $months = 6): array
    {
        $months = min(max($months, 1), 24);
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = now()->subMonths($i)->endOfMonth();

            $data[] = [
                'month' => $start->format('Y-m'),
                // 预约按就诊日期归桶（预约可在就诊前任意时间创建，按 created_at 会算错月份）
                'appointments' => Appointment::whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])->count(),
                'prescriptions' => Prescription::whereBetween('created_at', [$start, $end])->count(),
                'ai_diagnoses' => AiDiagnosis::whereBetween('created_at', [$start, $end])->count(),
                'medical_records' => MedicalRecord::whereBetween('created_at', [$start, $end])->count(),
                'new_patients' => User::where('role', 'patient')->whereBetween('created_at', [$start, $end])->count(),
            ];
        }

        return $data;
    }

    /**
     * 药品库存概览
     */
    public function drugOverview(): array
    {
        return [
            'total_drugs' => Drug::count(),
            'total_stock_value' => Drug::sum(DB::raw('stock_quantity * price')),
            'low_stock_count' => Drug::where('stock_quantity', '<', DrugService::lowStockThreshold())->count(),
            'out_of_stock_count' => Drug::where('stock_quantity', 0)->count(),
            'stock_in_this_month' => StockMovement::where('type', 'in')->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('quantity'),
            'stock_out_this_month' => StockMovement::where('type', 'out')->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('quantity'),
        ];
    }
}
