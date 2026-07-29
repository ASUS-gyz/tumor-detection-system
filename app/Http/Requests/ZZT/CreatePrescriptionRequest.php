<?php

namespace App\Http\Requests\ZZT;

use App\Http\Requests\BaseRequest;

/**
 * 开具处方 — 表单验证
 *
 * POST /api/doctor/prescriptions
 */
class CreatePrescriptionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.drug_id' => ['required', 'integer', 'exists:drugs,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.dosage' => ['required', 'string', 'max:255'],
            'items.*.instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'appointment_id.required' => '请指定关联预约',
            'appointment_id.exists' => '预约不存在',
            'items.required' => '请至少添加一种药品',
            'items.min' => '请至少添加一种药品',
            'items.*.drug_id.required' => '药品ID不能为空',
            'items.*.drug_id.exists' => '药品不存在',
            'items.*.quantity.required' => '药品数量不能为空',
            'items.*.quantity.min' => '药品数量必须大于0',
            'items.*.dosage.required' => '用量说明不能为空',
        ];
    }
}
