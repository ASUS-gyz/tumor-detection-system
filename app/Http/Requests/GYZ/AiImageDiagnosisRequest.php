<?php

namespace App\Http\Requests\GYZ;

use App\Http\Requests\BaseRequest;

class AiImageDiagnosisRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'patient_id' => 'required|integer|exists:users,id',
            'appointment_id' => 'nullable|integer|exists:appointments,id',
            'image' => 'required|file|mimes:jpg,jpeg,png,dcm|max:20480',
            'description' => 'required|string|min:10|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'image.max' => '文件大小不能超过20MB',
            'image.mimes' => '仅支持 jpg/png/dicom 格式',
        ];
    }
}
