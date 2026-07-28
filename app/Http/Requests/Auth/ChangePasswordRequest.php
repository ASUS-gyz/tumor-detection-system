<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

/**
 * 修改密码 — 表单验证
 *
 * PUT /api/auth/password
 */
class ChangePasswordRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'max:100', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => '当前密码为必填项',
            'new_password.required' => '新密码为必填项',
            'new_password.min' => '新密码长度不能少于6位',
            'new_password.max' => '新密码长度不能超过100位',
            'new_password.confirmed' => '两次输入的新密码不一致',
        ];
    }
}
