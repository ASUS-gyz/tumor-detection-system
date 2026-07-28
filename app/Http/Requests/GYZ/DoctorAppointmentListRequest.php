<?php

namespace App\Http\Requests\GYZ;

use App\Http\Requests\BaseRequest;

class DoctorAppointmentListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'page' => 'integer|min:1',
            'size' => 'integer|min:1|max:100',
            'date' => 'date_format:Y-m-d',
            'status' => 'in:pending,called,in_progress,completed,cancelled',
            'patient_name' => 'string|max:50',
        ];
    }
}
