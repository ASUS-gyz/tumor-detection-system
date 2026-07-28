<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * API Token 模型
 *
 * 模拟 Laravel Sanctum 的 PersonalAccessToken，
 * 用于 token 认证的存储与查询。
 */
class PersonalAccessToken extends Model
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'json',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * 获取拥有此 token 的模型（多态关联）
     */
    public function tokenable()
    {
        return $this->morphTo();
    }

    /**
     * 根据明文 token 查找记录
     */
    public static function findToken(string $token): ?static
    {
        return static::where('token', hash('sha256', $token))->first();
    }
}
