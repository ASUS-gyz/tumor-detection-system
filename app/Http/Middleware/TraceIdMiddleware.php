<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TraceIdMiddleware
{
    /**
     * 为每个请求生成唯一 TraceId，用于链路追踪
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $traceId = (string) Str::uuid();

        // 存入当前 Request 对象
        $request->attributes->set('trace_id', $traceId);

        // 写入日志上下文，后续所有日志自动携带
        Log::withContext([
            'trace_id' => $traceId,
        ]);

        return $next($request);
    }
}
