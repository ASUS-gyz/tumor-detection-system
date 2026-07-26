<?php

namespace App\Exceptions;

use App\Enums\ResponseCode;
use RuntimeException;

/**
 * 业务异常类
 *
 * Service 层主动抛出的业务异常，由 Handler 统一捕获并返回友好提示
 */
class BusinessException extends RuntimeException
{
    /**
     * @param string $message 错误消息
     * @param ResponseCode $codeEnum 错误码枚举
     */
    public function __construct(
        string $message = '业务处理失败',
        public readonly ResponseCode $codeEnum = ResponseCode::BUSINESS_ERROR,
    ) {
        parent::__construct($message, $codeEnum->value);
    }
}
