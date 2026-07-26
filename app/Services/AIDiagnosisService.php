<?php

namespace App\Services;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\Log;

/**
 * AI 诊断服务
 *
 * 提供文字诊断（患者端）和图文诊断（医生端）能力
 */
class AIDiagnosisService extends BaseService
{
    /**
     * AI 文字智能诊断（患者端）
     *
     * 患者输入症状描述，返回通俗病情分析
     */
    public function textDiagnosis(string $symptomDescription, int $userId): array
    {
        Log::channel('business')->info('AI文字诊断请求', [
            'user_id' => $userId,
            'symptom_length' => mb_strlen($symptomDescription),
        ]);

        if (config('ai.mode') === 'mock') {
            return $this->mockTextDiagnosis($symptomDescription);
        }

        // TODO: 对接真实 AI API
        return $this->callRemoteAI($symptomDescription, 'text');
    }

    /**
     * AI 图文进阶诊断（医生端）
     *
     * 医生上传 CT 影像 + 病情描述，返回专业医疗报告
     */
    public function imageDiagnosis(string $imagePath, string $description, int $doctorId, int $patientId): array
    {
        Log::channel('business')->info('AI图文诊断请求', [
            'doctor_id' => $doctorId,
            'patient_id' => $patientId,
            'image' => $imagePath,
        ]);

        if (config('ai.mode') === 'mock') {
            return $this->mockImageDiagnosis($description);
        }

        // TODO: 对接真实 AI API（上传图片 + 描述）
        return $this->callRemoteAI(['image' => $imagePath, 'description' => $description], 'image');
    }

    /**
     * 模拟文字诊断（开发阶段）
     */
    private function mockTextDiagnosis(string $symptomDescription): array
    {
        $delay = (int) config('ai.mock.text_diagnosis_delay', 2);
        sleep(min($delay, 5)); // 最大不超过 5 秒

        return [
            'analysis' => '根据您的描述，可能存在以下情况：' . mb_substr($symptomDescription, 0, 30) . '...',
            'risk_level' => '低风险',
            'risk_warning' => '建议进一步进行影像学检查以明确诊断。',
            'advice' => '1. 建议尽快预约肿瘤科门诊进行专业检查；2. 保持良好的生活习惯；3. 如症状持续或加重，请及时就医。',
            'possible_conditions' => ['良性肿瘤可能性较大', '建议定期复查'],
        ];
    }

    /**
     * 模拟图文诊断（开发阶段）
     */
    private function mockImageDiagnosis(string $description): array
    {
        $delay = (int) config('ai.mock.image_diagnosis_delay', 3);
        sleep(min($delay, 5));

        return [
            'imaging_features' => 'CT影像显示：局部组织密度改变，边界尚清晰，未见明显浸润征象。',
            'risk_assessment' => '中度风险',
            'suspected_lesions' => '疑似占位性病变，建议结合临床进一步评估。',
            'treatment_recommendations' => '1. 建议进行增强CT或MRI进一步明确；2. 必要时行穿刺活检；3. 请结合肿瘤标志物综合判断。',
            'confidence' => '85%',
        ];
    }

    /**
     * 调用远程 AI API（预留）
     */
    private function callRemoteAI(mixed $input, string $type): array
    {
        // TODO: 对接真实 AI 接口
        throw new BusinessException(
            'AI 远程服务暂未配置，请使用模拟模式',
            ResponseCode::THIRD_PARTY_ERROR
        );
    }
}
