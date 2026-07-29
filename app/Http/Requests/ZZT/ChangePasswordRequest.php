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
            'new_password' => [
                'required',
                'string',
                'min:8',
                'max:100',
                'confirmed',
                $this->passwordComplexityRule(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => '当前密码为必填项',
            'new_password.required' => '新密码为必填项',
            'new_password.min' => '新密码长度不能少于8位',
            'new_password.max' => '新密码长度不能超过100位',
            'new_password.confirmed' => '两次输入的新密码不一致',
        ];
    }

    private function passwordComplexityRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $types = 0;
            if (preg_match('/[A-Z]/', (string) $value)) { $types++; }
            if (preg_match('/[a-z]/', (string) $value)) { $types++; }
            if (preg_match('/[0-9]/', (string) $value)) { $types++; }
            if (preg_match('/[!@#$%^&*()_+\-=\[\]{}|;\':",.\/?]/', (string) $value)) { $types++; }
            if ($types < 3) {
                $fail('密码必须包含大写字母、小写字母、数字、特殊符号中至少3种类型');
            }
        };
    }
}
