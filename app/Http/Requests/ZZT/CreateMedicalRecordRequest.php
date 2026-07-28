<?php

namespace App\Http\Requests\Doctor;

use App\Http\Requests\BaseRequest;

/**
 * 创建病历 — 表单验证
 *
 * POST /api/doctor/medical-records
 */
class CreateMedicalRecordRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'symptoms' => ['required', 'string', 'min:2', 'max:5000'],
            'imaging_findings' => ['nullable', 'string', 'max:5000'],
            'preliminary_diagnosis' => ['required', 'string', 'min:2', 'max:2000'],
            'treatment_plan' => ['required', 'string', 'min:2', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'appointment_id.required' => '请指定关联预约',
            'appointment_id.exists' => '预约不存在',
            'symptoms.required' => '请输入症状描述',
            'symptoms.min' => '症状描述至少需要2个字符',
            'preliminary_diagnosis.required' => '请输入初步诊断',
            'preliminary_diagnosis.min' => '初步诊断至少需要2个字符',
            'treatment_plan.required' => '请输入诊疗医嘱',
            'treatment_plan.min' => '诊疗医嘱至少需要2个字符',
        ];
    }
}
