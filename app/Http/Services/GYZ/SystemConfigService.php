<?php

namespace App\Http\Services\GYZ;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\SystemConfig;

class SystemConfigService
{
    /**
     * 获取所有配置（按分组）
     */
    public function all(): array
    {
        return SystemConfig::select(['id', 'key', 'value', 'description', 'group'])
            ->orderBy('group')
            ->orderBy('id')
            ->get()
            ->groupBy('group')
            ->toArray();
    }

    /**
     * 批量更新配置（仅允许修改已存在的配置项，不静默新建任意 key）
     */
    public function update(array $configs): array
    {
        $known = SystemConfig::pluck('key')->all();
        $unknown = [];
        foreach ($configs as $item) {
            if (isset($item['key'], $item['value']) && ! in_array($item['key'], $known, true)) {
                $unknown[] = $item['key'];
            }
        }
        if ($unknown) {
            throw new BusinessException('配置项不存在：' . implode('、', $unknown), ResponseCode::PARAM_ERROR);
        }

        foreach ($configs as $item) {
            if (isset($item['key'], $item['value'])) {
                SystemConfig::set($item['key'], (string) $item['value']);
            }
        }

        return $this->all();
    }

    /**
     * 获取单个配置值
     */
    public static function getVal(string $key, $default = null): ?string
    {
        return SystemConfig::get($key, $default);
    }
}
