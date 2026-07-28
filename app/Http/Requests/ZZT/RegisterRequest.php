<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

/**
 * 患者注册 — 表单验证
 *
 * POST /api/auth/register
 */
class RegisterRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'between:2,50'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:/^1[3-9]\d{9}$/', 'unique:users,phone'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '姓名为必填项',
            'name.between' => '姓名长度须在2-50个字符之间',
            'email.required' => '邮箱为必填项',
            'email.email' => '邮箱格式不正确',
            'email.unique' => '该邮箱已被注册',
            'password.required' => '密码为必填项',
            'password.min' => '密码长度不能少于6位',
            'password.max' => '密码长度不能超过100位',
            'phone.regex' => '手机号格式不正确',
            'phone.unique' => '该手机号已被使用',
        ];
    }
}
