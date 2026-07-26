<?php

namespace App\Http\Requests;

use App\Enums\ResponseCode;
use App\Support\Result;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * 基础请求验证类
 *
 * 开发手册规范 — Request 层职责：
 * - 参数合法性验证
 * - 参数格式转换
 * - 参数默认值处理
 * - 权限预检查（可选）
 *
 * 禁止在 Request 中：
 * - 数据库查询（User::query()、DB::table()）
 * - 日志记录（Log::info()）
 * - 缓存操作（Redis::get()）
 * - 复杂业务判断
 */
abstract class BaseRequest extends FormRequest
{
    /**
     * 默认所有请求需要授权
     * 子类可覆盖此方法实现具体授权逻辑
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 验证失败统一响应格式
     *
     * 覆盖父类方法，使用开发手册规范的 Result 格式返回
     */
    protected function failedValidation(Validator $validator): void
    {
        // 取第一条错误信息
        $firstError = collect($validator->errors()->toArray())->flatten()->first();

        throw new ValidationException(
            $validator,
            Result::error(ResponseCode::PARAM_ERROR, $firstError)
        );
    }
}
