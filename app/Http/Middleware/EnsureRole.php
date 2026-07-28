<?php

namespace App\Http\Middleware;

use App\Enums\ResponseCode;
use App\Support\Result;
use Closure;
use Illuminate\Http\Request;

/**
 * 角色权限中间件
 *
 * 验证当前登录用户是否具有指定角色。
 * 用法：Route::middleware('auth:sanctum', 'role:patient')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles)) {
            return Result::error(ResponseCode::FORBIDDEN, '无访问权限');
        }

        return $next($request);
    }
}
