<?php

namespace App\Http\Requests\GYZ;

use App\Http\Requests\BaseRequest;

class AdminUserCreateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:50',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6|max:100',
            'role' => 'required|in:doctor,admin',
            'phone' => 'nullable|string|max:20',
        ];
    }
}
