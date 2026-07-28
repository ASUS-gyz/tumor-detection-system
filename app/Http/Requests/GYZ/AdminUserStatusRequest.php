<?php

namespace App\Http\Requests\GYZ;

use App\Http\Requests\BaseRequest;

class AdminUserStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => 'required|in:active,disabled',
        ];
    }
}
