<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

/**
 * 用户登录 — 表单验证
 *
 * POST /api/auth/login
 */
class LoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => '邮箱为必填项',
            'email.email' => '邮箱格式不正确',
            'password.required' => '密码为必填项',
        ];
    }
}
