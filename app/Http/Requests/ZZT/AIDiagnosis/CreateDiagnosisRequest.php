<?php

namespace App\Http\Requests\ZZT\AIDiagnosis;

use App\Http\Requests\BaseRequest;

/**
 * AI 文字诊断 — 表单验证
 *
 * POST /api/patient/ai-diagnosis
 */
class CreateDiagnosisRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'symptom_description' => ['required', 'string', 'min:2', 'max:2000'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'symptom_description.required' => '请输入症状描述',
            'symptom_description.min' => '症状描述至少需要2个字符',
            'symptom_description.max' => '症状描述不能超过2000个字符',
            'appointment_id.exists' => '关联预约不存在',
        ];
    }
}
