<?php

namespace App\Enums;

/**
 * 统一响应码枚举
 *
 * 所有接口返回的 code 字段必须使用此枚举值
 */
enum ResponseCode: int
{
    /**
     * 成功
     */
    case SUCCESS = 0;

    /**
     * 参数异常 (10000段)
     */
    case PARAM_ERROR = 10001;
    case PARAM_MISSING = 10002;
    case PARAM_FORMAT_ERROR = 10003;
    case PARAM_OUT_OF_RANGE = 10004;
    case PARAM_ILLEGAL = 10005;
    case FILE_FORMAT_ERROR = 10006;
    case FILE_TOO_LARGE = 10007;
    case UPLOAD_FAILED = 10008;

    /**
     * 认证授权 (20000段)
     */
    case UNAUTHORIZED = 20001;
    case TOKEN_EXPIRED = 20002;
    case TOKEN_ERROR = 20003;
    case LOGIN_EXPIRED = 20004;
    case FORBIDDEN = 20005;
    case ACCOUNT_DISABLED = 20006;
    case ACCOUNT_FROZEN = 20007;
    case PASSWORD_ERROR = 20008;

    /**
     * 数据相关 (30000段)
     */
    case DATA_NOT_FOUND = 30001;
    case DATA_DELETED = 30002;
    case DATA_DUPLICATE = 30003;
    case DATA_STATUS_ERROR = 30004;
    case DATA_LOCKED = 30005;
    case DATA_RELATION_EXISTS = 30006;
    case DATA_VALIDATION_FAILED = 30007;
    case DATA_VERSION_CONFLICT = 30008;

    /**
     * 业务异常 (40000段)
     */
    case BUSINESS_ERROR = 40001;
    case STATUS_NOT_ALLOWED = 40002;
    case APPROVAL_NOT_PASSED = 40003;
    case STOCK_NOT_ENOUGH = 40004;
    case AMOUNT_EXCEEDED = 40005;
    case QUOTA_EXCEEDED = 40006;
    case ALREADY_SUBMITTED = 40007;
    case COMPLETED_CANNOT_MODIFY = 40008;
    case DUPLICATE_SUBMIT = 40009;
    case BUSINESS_RULE_LIMIT = 40010;

    /**
     * 第三方服务 (50000段)
     */
    case THIRD_PARTY_ERROR = 50001;
    case SMS_SEND_FAILED = 50003;
    case EMAIL_SEND_FAILED = 50004;
    case OSS_UPLOAD_FAILED = 50005;
    case REDIS_ERROR = 50006;
    case MQ_ERROR = 50007;
    case THIRD_PARTY_TIMEOUT = 50008;

    /**
     * 数据库异常 (60000段)
     */
    case DATABASE_ERROR = 60001;
    case SQL_ERROR = 60002;
    case TRANSACTION_FAILED = 60003;
    case TRANSACTION_ROLLBACK = 60004;
    case UNIQUE_INDEX_CONFLICT = 60005;
    case FOREIGN_KEY_ERROR = 60006;
    case DEADLOCK_ERROR = 60007;
    case DATABASE_TIMEOUT = 60008;

    /**
     * 系统异常 (90000段)
     */
    case SYSTEM_ERROR = 90001;
    case UNKNOWN_ERROR = 90002;
    case RUNTIME_ERROR = 90003;
    case SERVICE_BUSY = 90004;
    case MAINTENANCE = 90005;
    case CONFIG_ERROR = 90006;
    case FILE_IO_ERROR = 90007;
    case SERVER_INTERNAL_ERROR = 90008;

    /**
     * 获取错误消息
     */
    public function msg(): string
    {
        return match ($this) {
            self::SUCCESS => '成功',

            self::PARAM_ERROR => '参数错误',
            self::PARAM_MISSING => '必填参数缺失',
            self::PARAM_FORMAT_ERROR => '参数格式错误',
            self::PARAM_OUT_OF_RANGE => '参数超出范围',
            self::PARAM_ILLEGAL => '非法参数',
            self::FILE_FORMAT_ERROR => '文件格式错误',
            self::FILE_TOO_LARGE => '文件过大',
            self::UPLOAD_FAILED => '上传失败',

            self::UNAUTHORIZED => '未登录',
            self::TOKEN_EXPIRED => 'Token已失效',
            self::TOKEN_ERROR => 'Token错误',
            self::LOGIN_EXPIRED => '登录已过期',
            self::FORBIDDEN => '无访问权限',
            self::ACCOUNT_DISABLED => '账号被禁用',
            self::ACCOUNT_FROZEN => '账号被冻结',
            self::PASSWORD_ERROR => '密码错误',

            self::DATA_NOT_FOUND => '记录不存在',
            self::DATA_DELETED => '数据已删除',
            self::DATA_DUPLICATE => '数据重复',
            self::DATA_STATUS_ERROR => '数据状态异常',
            self::DATA_LOCKED => '数据已锁定',
            self::DATA_RELATION_EXISTS => '数据关联存在',
            self::DATA_VALIDATION_FAILED => '数据校验失败',
            self::DATA_VERSION_CONFLICT => '数据已被他人修改，请刷新后重试',

            self::BUSINESS_ERROR => '业务处理失败',
            self::STATUS_NOT_ALLOWED => '当前状态不可操作',
            self::APPROVAL_NOT_PASSED => '审批未通过',
            self::STOCK_NOT_ENOUGH => '库存不足',
            self::AMOUNT_EXCEEDED => '金额超限',
            self::QUOTA_EXCEEDED => '超出配额',
            self::ALREADY_SUBMITTED => '已提交审核',
            self::COMPLETED_CANNOT_MODIFY => '已完成不可修改',
            self::DUPLICATE_SUBMIT => '请勿重复提交',
            self::BUSINESS_RULE_LIMIT => '超出业务规则限制',

            self::THIRD_PARTY_ERROR => '第三方服务异常',
            self::SMS_SEND_FAILED => '短信发送失败',
            self::EMAIL_SEND_FAILED => '邮件发送失败',
            self::OSS_UPLOAD_FAILED => 'OSS上传失败',
            self::REDIS_ERROR => 'Redis连接失败',
            self::MQ_ERROR => 'MQ消息发送失败',
            self::THIRD_PARTY_TIMEOUT => '第三方接口超时',

            self::DATABASE_ERROR => '数据库异常',
            self::SQL_ERROR => 'SQL执行失败',
            self::TRANSACTION_FAILED => '事务提交失败',
            self::TRANSACTION_ROLLBACK => '事务回滚',
            self::UNIQUE_INDEX_CONFLICT => '数据重复，请检查后提交',
            self::FOREIGN_KEY_ERROR => '外键约束失败',
            self::DEADLOCK_ERROR => '死锁异常',
            self::DATABASE_TIMEOUT => '数据库超时',

            self::SYSTEM_ERROR => '系统异常，请联系管理员',
            self::UNKNOWN_ERROR => '未知错误',
            self::RUNTIME_ERROR => '程序运行异常',
            self::SERVICE_BUSY => '服务繁忙',
            self::MAINTENANCE => '系统维护中',
            self::CONFIG_ERROR => '配置错误',
            self::FILE_IO_ERROR => '文件读写失败',
            self::SERVER_INTERNAL_ERROR => '服务器内部错误',
        };
    }
}
