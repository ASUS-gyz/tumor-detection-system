<?php

namespace App\Http\Requests\ZZT;

use App\Http\Requests\BaseRequest;

/**
 * 更新个人资料 — 表单验证
 *
 * PUT /api/auth/profile
 */
class UpdateProfileRequest extends BaseRequest
{
    public function rules(): array
    {
        $rules = [
            'name' => ['nullable', 'string', 'between:2,50'],
            'phone' => ['nullable', 'string', 'regex:/^1[3-9]\d{9}$/', 'unique:users,phone,' . $this->user()->id],
            // 医生专属字段
            'title' => ['nullable', 'string', 'max:50'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:100'],
            'introduction' => ['nullable', 'string'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.between' => '姓名长度须在2-50个字符之间',
            'phone.regex' => '手机号格式不正确',
            'phone.unique' => '该手机号已被使用',
            'title.max' => '职称不能超过50个字符',
            'specialty.max' => '专长不能超过255个字符',
            'department.max' => '科室不能超过100个字符',
            'experience_years.integer' => '从业年限须为整数',
            'experience_years.min' => '从业年限不能小于0',
            'experience_years.max' => '从业年限不能超过60',
        ];
    }

    /**
     * 获取仅医生可更新的字段列表
     */
    public function doctorFields(): array
    {
        return ['title', 'specialty', 'department', 'introduction', 'experience_years'];
    }
}
