<?php

namespace App\Http\Middleware;

use App\Enums\ResponseCode;
use App\Support\Result;
use Closure;
use Illuminate\Http\Request;

/**
 * 角色权限中间件
 *
 * 用法：Route::middleware('role:doctor') — 仅允许 doctor 角色访问
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role)
    {
        if (! auth()->check()) {
            return Result::error(ResponseCode::UNAUTHORIZED);
        }

        if (auth()->user()->role !== $role) {
            return Result::error(ResponseCode::FORBIDDEN, '当前账号无此操作权限');
        }

        return $next($request);
    }
}
