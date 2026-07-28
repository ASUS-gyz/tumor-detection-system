<?php

namespace App\Auth;

use App\Models\PersonalAccessToken;
use Illuminate\Support\Str;

/**
 * API Token 能力 trait
 *
 * 模拟 Laravel Sanctum 的 HasApiTokens，
 * 提供 createToken / currentAccessToken / tokens 等功能。
 *
 * 用法：在 User 模型中 use HasApiTokens;
 */
trait HasApiTokens
{
    /**
     * 当前请求使用的 token 实例（由 guard 注入）
     */
    public ?PersonalAccessToken $currentAccessToken = null;

    /**
     * 用户的所有 token（多态关联）
     */
    public function tokens()
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }

    /**
     * 创建一个新的 API token
     *
     * @param string $name token 名称（如 "auth_token"）
     * @param array $abilities 权限列表（预留）
     * @return array{token: PersonalAccessToken, plainTextToken: string}
     */
    public function createToken(string $name, array $abilities = ['*']): array
    {
        $plainTextToken = Str::random(40);

        $token = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
        ]);

        return [
            'token' => $token,
            'plainTextToken' => $plainTextToken,
        ];
    }

    /**
     * 获取当前请求的 token 实例
     */
    public function currentAccessToken(): ?PersonalAccessToken
    {
        return $this->currentAccessToken;
    }

    /**
     * 检查当前 token 是否具有指定能力
     */
    public function tokenCan(string $ability): bool
    {
        return $this->currentAccessToken
            && in_array('*', $this->currentAccessToken->abilities ?? [])
            || in_array($ability, $this->currentAccessToken->abilities ?? []);
    }
}
