<?php

namespace App\Http\Requests\ZZT;

use App\Http\Requests\BaseRequest;

/**
 * 编辑病历 — 表单验证
 *
 * PUT /api/doctor/medical-records/{id}
 */
class UpdateMedicalRecordRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'symptoms' => ['nullable', 'string', 'min:2', 'max:5000'],
            'imaging_findings' => ['nullable', 'string', 'max:5000'],
            'preliminary_diagnosis' => ['nullable', 'string', 'min:2', 'max:2000'],
            'treatment_plan' => ['nullable', 'string', 'min:2', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'symptoms.min' => '症状描述至少需要2个字符',
            'preliminary_diagnosis.min' => '初步诊断至少需要2个字符',
            'treatment_plan.min' => '诊疗医嘱至少需要2个字符',
        ];
    }
}
