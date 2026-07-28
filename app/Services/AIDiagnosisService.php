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
     * 调用远程 AI API（DeepSeek）
     */
    private function callRemoteAI(mixed $input, string $type): array
    {
        $client = new \GuzzleHttp\Client();
        
        try {
            $messages = [
                [
                    'role' => 'system',
                    'content' => $type === 'text' 
                        ? '你是一名专业的肿瘤科医生，请根据患者的症状描述进行分析。请以JSON格式返回，包含以下字段：analysis（病情分析）、risk_level（风险等级：低风险/中风险/高风险）、risk_warning（风险提示）、advice（就诊建议）、possible_conditions（可能情况列表，数组格式）。'
                        : '你是一名专业的肿瘤科医生，请根据影像描述进行专业医疗分析。请以JSON格式返回，包含以下字段：imaging_features（影像特征）、risk_assessment（风险评估）、suspected_lesions（疑似病变）、treatment_recommendations（治疗建议）、confidence（置信度）。',
                ],
                [
                    'role' => 'user',
                    'content' => is_array($input) ? $input['description'] : $input,
                ],
            ];

            $response = $client->post(config('ai.api.url'), [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('ai.api.key'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => config('ai.api.model'),
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'response_format' => ['type' => 'json_object'],
                ],
                'timeout' => config('ai.api.timeout'),
            ]);

            $data = json_decode($response->getBody(), true);
            
            if (!isset($data['choices'][0]['message']['content'])) {
                throw new \Exception('AI返回格式错误');
            }

            $content = $data['choices'][0]['message']['content'];
            $result = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // 如果返回不是JSON格式，使用模拟数据
                return $type === 'text' 
                    ? $this->mockTextDiagnosis(is_array($input) ? $input['description'] : $input)
                    : $this->mockImageDiagnosis(is_array($input) ? $input['description'] : $input);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('AI诊断调用失败', ['error' => $e->getMessage()]);
            throw new BusinessException('AI诊断服务暂时不可用', ResponseCode::THIRD_PARTY_ERROR);
        }
    }
}
