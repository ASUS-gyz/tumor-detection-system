<?php

namespace App\Http\Services\GYZ;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\AiDiagnosis;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DoctorAiDiagnosisService
{
    /**
     * AI 图文诊断
     */
    public function create(int $doctorId, int $patientId, ?int $appointmentId, UploadedFile $image, string $description): array
    {
        $patient = User::select(['id', 'name'])->find($patientId);
        if (! $patient) {
            throw new BusinessException('患者不存在', ResponseCode::DATA_NOT_FOUND);
        }

        $path = $image->store('ai-images', 'public');
        $imageUrl = Storage::url($path);

        // remote 模式：调用千问 VL
        if (config('ai.mode') === 'remote') {
            $qwen = app(QwenVisionService::class);
            $result = $qwen->analyze($image, $description);
        } else {
            // mock 模式
            sleep((int) config('ai.mock.image_diagnosis_delay', 2));
            $result = [
                'imaging_features'          => 'CT影像显示：右肺上叶后段见约2.5cm×1.8cm结节影，边界欠清，呈分叶状，密度不均匀，可见毛刺征及胸膜凹陷征。邻近胸膜轻度增厚。增强扫描示结节呈不均匀强化。',
                'risk_assessment'           => '高风险',
                'suspected_lesions'         => '结合影像学特征（分叶、毛刺、胸膜凹陷、不均匀强化），右肺上叶周围型肺癌可能性大（T1cN0M0？），建议结合病理学检查明确诊断。',
                'treatment_recommendations' => "1. 建议立即行CT引导下经皮肺穿刺活检明确病理；2. 完善PET-CT进行全身评估排除远处转移；3. 查肿瘤标志物全套（CEA、CYFRA21-1、NSE、SCC）；4. 肺功能检查评估手术耐受性；5. 请胸外科及放疗科多学科会诊。",
                'confidence'                => '92%',
            ];
        }

        $diag = AiDiagnosis::create([
            'type' => 'image',
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'appointment_id' => $appointmentId,
            'description' => $description,
            'imaging_features' => $result['imaging_features'],
            'risk_assessment' => $result['risk_assessment'],
            'suspected_lesions' => $result['suspected_lesions'],
            'treatment_recommendations' => $result['treatment_recommendations'],
            'confidence' => $result['confidence'],
            'image_url' => $imageUrl,
        ]);

        Log::channel('business')->info('AI图文诊断完成', [
            'doctor_id' => $doctorId,
            'patient_id' => $patientId,
            'diagnosis_id' => $diag->id,
        ]);

        return [
            'id' => $diag->id,
            'type' => 'image',
            'patient_id' => $diag->patient_id,
            'patient_name' => $patient->name,
            'appointment_id' => $diag->appointment_id,
            'imaging_features' => $diag->imaging_features,
            'risk_assessment' => $diag->risk_assessment,
            'suspected_lesions' => $diag->suspected_lesions,
            'treatment_recommendations' => $diag->treatment_recommendations,
            'confidence' => $diag->confidence,
            'image_url' => $diag->image_url,
            'created_at' => $diag->created_at->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * AI 图文诊断记录列表
     */
    public function list(int $doctorId, array $filters): LengthAwarePaginator
    {
        $query = AiDiagnosis::with('patient:id,name')
            ->where('doctor_id', $doctorId)
            ->where('type', 'image')
            ->select(['id', 'type', 'patient_id', 'risk_assessment', 'confidence', 'created_at']);

        if (! empty($filters['patient_name'])) {
            $query->whereHas('patient', fn ($p) => $p->where('name', 'like', "%{$filters['patient_name']}%"));
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->latest()
            ->paginate($filters['size'] ?? 10, page: $filters['page'] ?? 1)
            ->through(fn ($d) => [
                'id' => $d->id,
                'patient_name' => $d->patient?->name,
                'type' => $d->type,
                'risk_assessment' => $d->risk_assessment,
                'confidence' => $d->confidence,
                'created_at' => $d->created_at->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
            ]);
    }

    /**
     * AI 图文诊断报告详情
     */
    public function detail(int $doctorId, int $id): array
    {
        $diag = AiDiagnosis::with('patient:id,name')->where('doctor_id', $doctorId)->where('type', 'image')->find($id);
        if (! $diag) {
            throw new BusinessException('报告不存在', ResponseCode::DATA_NOT_FOUND);
        }

        return [
            'id' => $diag->id,
            'type' => $diag->type,
            'patient_id' => $diag->patient_id,
            'patient_name' => $diag->patient?->name,
            'appointment_id' => $diag->appointment_id,
            'description' => $diag->description,
            'imaging_features' => $diag->imaging_features,
            'risk_assessment' => $diag->risk_assessment,
            'suspected_lesions' => $diag->suspected_lesions,
            'treatment_recommendations' => $diag->treatment_recommendations,
            'confidence' => $diag->confidence,
            'image_url' => $diag->image_url,
            'created_at' => $diag->created_at->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
        ];
    }
}
