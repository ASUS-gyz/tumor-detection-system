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
        $query = Appointment::select('doctor_id', DB::raw('count(*) as total'), DB::raw("sum(case when status='completed' then 1 else 0 end) as completed"))
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
                'completion_rate' => $r->total > 0 ? round($r->completed / $r->total * 100, 1) : 0,
            ])
            ->values()
            ->toArray();
    }

    /**
     * 药品消耗统计
     */
    public function drugConsumption(?string $dateFrom = null, ?string $dateTo = null): array
    {
        // GYZ 入库/出库记录
        $gyz = StockMovement::with('drug:id,name,specification')
            ->where('type', 'out')
            ->select('drug_id', DB::raw('sum(quantity) as total_out'), DB::raw('count(*) as times'))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->groupBy('drug_id')->get();

        // ZZT 取药出库记录（drug_stock_changes 表）
        $zzt = DB::table('drug_stock_changes')
            ->where('type', 'out')
            ->select('drug_id', DB::raw('sum(quantity) as total_out'), DB::raw('count(*) as times'))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->groupBy('drug_id')->get();

        // 合并
        $merged = [];
        foreach ($gyz as $r) {
            $merged[$r->drug_id] = [
                'drug_id' => $r->drug_id, 'drug_name' => $r->drug?->name, 'specification' => $r->drug?->specification,
                'total_dispensed' => (int) $r->total_out, 'dispense_count' => (int) $r->times,
            ];
        }
        foreach ($zzt as $r) {
            $drug = Drug::find($r->drug_id);
            if (isset($merged[$r->drug_id])) {
                $merged[$r->drug_id]['total_dispensed'] += (int) $r->total_out;
                $merged[$r->drug_id]['dispense_count'] += (int) $r->times;
            } else {
                $merged[$r->drug_id] = [
                    'drug_id' => $r->drug_id, 'drug_name' => $drug?->name, 'specification' => $drug?->specification,
                    'total_dispensed' => (int) $r->total_out, 'dispense_count' => (int) $r->times,
                ];
            }
        }

        return array_values(collect($merged)->sortByDesc('total_dispensed')->toArray());
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
                'appointments' => Appointment::whereBetween('created_at', [$start, $end])->count(),
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
            'low_stock_count' => Drug::where('stock_quantity', '<', DrugService::LOW_STOCK_THRESHOLD)->count(),
            'out_of_stock_count' => Drug::where('stock_quantity', 0)->count(),
            'stock_in_this_month' => StockMovement::where('type', 'in')->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('quantity'),
            'stock_out_this_month' => StockMovement::where('type', 'out')->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('quantity'),
        ];
    }
}
