<?php

namespace App\Support\GYZ;

use Illuminate\Support\Carbon;

/**
 * GYZ 模块 — 北京时间格式化
 */
class DateHelper
{
    public const TZ = 'Asia/Shanghai';

    /**
     * Carbon → 北京时间 ISO8601 字符串（如 "2026-07-30T20:03:15+08:00"）
     */
    public static function iso(?Carbon $carbon): ?string
    {
        return $carbon?->setTimezone(self::TZ)->toIso8601String();
    }

    /**
     * Carbon → 北京时间 datetime（如 "2026-07-30 20:03:15"）
     */
    public static function datetime(?Carbon $carbon): ?string
    {
        return $carbon?->setTimezone(self::TZ)->format('Y-m-d H:i:s');
    }

    /**
     * Carbon → 日期字符串（如 "2026-07-30"）
     */
    public static function dateStr(?Carbon $carbon): ?string
    {
        return $carbon?->setTimezone(self::TZ)->format('Y-m-d');
    }
}
