<?php

namespace App\Http\Services\GYZ;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Http\Services\ZZT\AIDiagnosisService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 千问 VL 图文诊断服务
 *
 * 调用阿里云 DashScope API，将 CT/X 光影像 + 病情描述发给千问 VL 模型，
 * 返回结构化的肿瘤诊断结果。
 */
class QwenVisionService
{
    /**
     * 分析一张医学影像
     *
     * @return array{imaging_features:string, risk_assessment:string, suspected_lesions:string, treatment_recommendations:string, confidence:string}
     */
    public function analyze(UploadedFile $image, string $description): array
    {
        $base64 = base64_encode(file_get_contents($image->getRealPath()));
        $mime   = $image->getMimeType() ?: 'image/jpeg';

        $payload = [
            'model' => config('ai.qwen.model'),
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$base64}"]],
                        ['type' => 'text', 'text' => "病情描述：{$description}\n\n请按上述要求给出诊断结果。"],
                    ],
                ],
            ],
        ];

        Log::channel('business')->info('调用千问VL图文诊断', [
            'model'      => config('ai.qwen.model'),
            'desc_len'   => mb_strlen($description),
            'image_size' => $image->getSize(),
        ]);

        $start  = microtime(true);
        try {
            $resp = Http::timeout(config('ai.qwen.timeout'))
                ->withToken(config('ai.qwen.key'))
                ->acceptJson()
                ->post(config('ai.qwen.url'), $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::channel('exception')->error('千问VL连接失败', ['error' => $e->getMessage()]);
            throw new BusinessException('AI 诊断服务连接失败，请稍后重试', ResponseCode::THIRD_PARTY_ERROR);
        }
        $elapsed = round((microtime(true) - $start) * 1000);

        Log::channel('api')->info('千问VL响应', [
            'status'    => $resp->status(),
            'elapsed_ms' => $elapsed,
        ]);

        if (! $resp->successful()) {
            Log::channel('exception')->error('千问VL调用失败', [
                'status' => $resp->status(),
                'body'   => $resp->body(),
            ]);
            throw new BusinessException('AI 诊断服务暂时不可用，请稍后重试', ResponseCode::THIRD_PARTY_ERROR);
        }

        return $this->parseResponse($resp->json());
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
你是一名资深的肿瘤科影像学专家。请根据上传的CT/MRI/X光影像和病情描述，给出专业的诊断分析。

请严格按以下JSON格式返回（不要包含markdown代码块标记，只输出纯JSON）：

{
    "imaging_features": "影像学特征描述，包括病灶位置、大小、形态、密度、边界、增强特征等，100-300字",
    "risk_assessment": "高风险/中风险/低风险",
    "suspected_lesions": "疑似病变诊断意见，列出最可能的诊断及鉴别诊断，100-300字",
    "treatment_recommendations": "治疗建议，包括进一步检查、会诊、治疗方案等，分条列出",
    "confidence": "诊断置信度，格式为 XX%"
}
PROMPT;
    }

    private function parseResponse(?array $body): array
    {
        // OpenAI 兼容格式: choices[0].message.content
        $text = $body['choices'][0]['message']['content'] ?? '';

        $text = trim((string) $text);
        if ($text === '') {
            throw new BusinessException('AI 返回内容为空', ResponseCode::THIRD_PARTY_ERROR);
        }

        // 清理可能的 markdown 代码块标记
        $text = preg_replace('/^```(?:json)?\s*\n?/i', '', $text);
        $text = preg_replace('/\n?```\s*$/i', '', $text);

        $parsed = json_decode($text, true);
        if (! is_array($parsed)) {
            throw new BusinessException('AI 返回格式异常', ResponseCode::THIRD_PARTY_ERROR);
        }

        // 千问可能把分条建议返回为数组，统一转成字符串
        $treatment = $parsed['treatment_recommendations'] ?? '';
        if (is_array($treatment)) {
            $treatment = implode('；', $treatment);
        }

        return [
            'imaging_features'          => is_array($parsed['imaging_features'] ?? null) ? implode('', $parsed['imaging_features']) : ($parsed['imaging_features'] ?? ''),
            // 风险词归一化为 低风险/中风险/高风险（远程模型可能返回"中度风险"等变体，无匹配归为 未知）
            'risk_assessment'           => AIDiagnosisService::normalizeRiskLevel((string) ($parsed['risk_assessment'] ?? '')),
            'suspected_lesions'         => is_array($parsed['suspected_lesions'] ?? null) ? implode('', $parsed['suspected_lesions']) : ($parsed['suspected_lesions'] ?? ''),
            'treatment_recommendations' => $treatment,
            'confidence'                => $parsed['confidence'] ?? 'N/A',
        ];
    }
}
