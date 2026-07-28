<?php

namespace App\Http\Requests\GYZ;

use App\Http\Requests\BaseRequest;

class AdminUserUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'string|min:2|max:50',
            'email' => 'email|max:255',
            'phone' => 'string|max:20',
            'role' => 'in:patient,doctor,admin',
        ];
    }
}
