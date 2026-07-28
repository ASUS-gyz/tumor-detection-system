<?php

namespace App\Auth;

use App\Models\PersonalAccessToken;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

/**
 * 自定义 Sanctum 风格的 Token Guard
 *
 * 从请求 Bearer Token 中提取 token，在 personal_access_tokens 表中查找，
 * 验证通过后将 User 设置为已认证用户。
 */
class SanctumGuard implements Guard
{
    use GuardHelpers;

    protected Request $request;

    public function __construct(UserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request = $request;
    }

    /**
     * 获取当前已认证用户
     */
    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->getTokenFromRequest();

        if (! $token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken) {
            return null;
        }

        // 检查 token 是否过期
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            $accessToken->delete();
            return null;
        }

        $user = $this->provider->retrieveById($accessToken->tokenable_id);

        if (! $user) {
            return null;
        }

        // 更新最后使用时间
        $accessToken->forceFill(['last_used_at' => now()])->save();

        // 注入当前 token 到用户模型
        if (method_exists($user, 'withAccessToken')) {
            $user->withAccessToken($accessToken);
        } elseif (in_array(HasApiTokens::class, class_uses_recursive($user))) {
            $user->currentAccessToken = $accessToken;
        }

        $this->user = $user;

        return $this->user;
    }

    /**
     * 验证用户凭据
     */
    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            $this->user = $user;
            return true;
        }

        return false;
    }

    /**
     * 从请求中提取 Bearer Token
     */
    protected function getTokenFromRequest(): ?string
    {
        $token = $this->request->bearerToken();

        if (! $token) {
            return null;
        }

        return $token;
    }

    /**
     * 设置当前用户
     */
    public function setUser(\Illuminate\Contracts\Auth\Authenticatable $user): void
    {
        $this->user = $user;
    }
}
