<?php

namespace App\Http\Requests\ZZT;

use App\Http\Requests\BaseRequest;

/**
 * 预约列表查询 — 表单验证
 *
 * GET /api/patient/appointments
 */
class AppointmentListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', 'string', 'in:pending,called,in_progress,completed,cancelled'],
            'date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.max' => '每页最多 50 条',
            'status.in' => '无效的预约状态',
            'date.date' => '日期格式不正确',
        ];
    }
}
