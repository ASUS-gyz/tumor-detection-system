<?php

namespace App\Http\Services\GYZ;

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
     * 批量更新配置
     */
    public function update(array $configs): array
    {
        foreach ($configs as $item) {
            if (isset($item['key'], $item['value'])) {
                SystemConfig::set($item['key'], $item['value']);
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
