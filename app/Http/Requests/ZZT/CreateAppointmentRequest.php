<?php

namespace App\Http\Requests\ZZT;

use App\Http\Requests\BaseRequest;

/**
 * 创建预约 — 表单验证
 *
 * POST /api/patient/appointments
 */
class CreateAppointmentRequest extends BaseRequest
{
    /** 允许的预约时段 */
    public const ALLOWED_TIMES = [
        '08:30', '09:15', '10:00', '10:45',
        '13:30', '14:15', '15:00', '15:45',
    ];

    public function rules(): array
    {
        return [
            'doctor_id' => ['required', 'integer', 'exists:users,id,role,doctor,status,active'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'appointment_time' => ['required', 'string', 'in:' . implode(',', self::ALLOWED_TIMES)],
        ];
    }

    public function messages(): array
    {
        return [
            'doctor_id.required' => '请选择医生',
            'doctor_id.exists' => '该医生不存在或已停诊',
            'appointment_date.required' => '请选择预约日期',
            'appointment_date.after_or_equal' => '预约日期不能早于今天',
            'appointment_time.required' => '请选择预约时段',
            'appointment_time.in' => '无效的预约时段',
        ];
    }
}
