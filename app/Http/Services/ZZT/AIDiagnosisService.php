<?php

namespace App\Http\Services\ZZT;

use App\Http\Services\BaseService;

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
        // mock 结果为预设数据，无需模拟真实 API 延迟阻塞请求
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
        return [
            'imaging_features' => 'CT影像显示：局部组织密度改变，边界尚清晰，未见明显浸润征象。',
            'risk_assessment' => '中风险',
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
        $client = new \GuzzleHttp\Client();
        try {
            $messages = [
                ['role' => 'system', 'content' => $type === 'text'
                    ? '你是肿瘤科医生，按JSON返回：analysis,risk_level(低/中/高风险),risk_warning,advice,possible_conditions(数组)'
                    : '你是肿瘤科医生，按JSON返回：imaging_features,risk_assessment,suspected_lesions,treatment_recommendations,confidence'],
                ['role' => 'user', 'content' => is_array($input) ? $input['description'] : $input],
            ];
            $response = $client->post(config('ai.qwen.url'), [
                'headers' => ['Authorization' => 'Bearer '.config('ai.qwen.key'), 'Content-Type' => 'application/json'],
                'json' => ['model' => config('ai.qwen.model'), 'messages' => $messages, 'temperature' => 0.7, 'response_format' => ['type' => 'json_object']],
                'timeout' => config('ai.qwen.timeout'),
            ]);
            $data = json_decode($response->getBody(), true);
            $result = json_decode($data['choices'][0]['message']['content'] ?? '{}', true);
            if (is_array($result)) {
                // 远程模型可能把文本字段返回为数组，非列表字段拍平为字符串，避免入库报 Array to string conversion
                foreach ($result as $k => $v) {
                    if (is_array($v) && $k !== 'possible_conditions') {
                        $result[$k] = implode('；', array_map(fn ($x) => is_scalar($x) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE), $v));
                    }
                }
                // 风险词归一化为 低风险/中风险/高风险（远程模型可能返回"中度风险""恶性"等变体）
                foreach (['risk_level', 'risk_assessment'] as $riskKey) {
                    if (isset($result[$riskKey])) {
                        $result[$riskKey] = $this->normalizeRiskLevel((string) $result[$riskKey]);
                    }
                }
            }
            return $result ?: ($type === 'text' ? $this->mockTextDiagnosis((string)$input) : $this->mockImageDiagnosis((string)($input['description'] ?? '')));
        } catch (\Exception $e) {
            Log::error('AI调用失败', ['error' => $e->getMessage()]);
            throw new BusinessException('AI诊断服务暂时不可用', ResponseCode::THIRD_PARTY_ERROR);
        }
    }

    /**
     * 风险等级归一化：取 高/中/低 关键字映射为标准三级词，无匹配时归为 未知
     */
    public static function normalizeRiskLevel(string $value): string
    {
        return match (true) {
            mb_strpos($value, '高') !== false => '高风险',
            mb_strpos($value, '中') !== false => '中风险',
            mb_strpos($value, '低') !== false => '低风险',
            default => '未知',
        };
    }
}
