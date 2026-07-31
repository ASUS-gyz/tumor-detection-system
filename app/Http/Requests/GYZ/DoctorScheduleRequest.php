<?php

namespace App\Http\Requests\GYZ;

use App\Http\Requests\BaseRequest;

class DoctorScheduleRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'day_of_week' => 'required|integer|min:0|max:6',
            'is_available' => 'boolean',
            'time_slots' => 'nullable|array',
            'time_slots.*' => 'string',
            'max_patients' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'day_of_week.required' => '请选择排班日期',
            'day_of_week.integer' => '日期格式错误',
            'day_of_week.min' => '日期范围：周日(0)~周六(6)',
            'day_of_week.max' => '日期范围：周日(0)~周六(6)',
            'max_patients.min' => '最大接诊人数至少为1',
        ];
    }
}
