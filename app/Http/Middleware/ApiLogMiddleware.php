<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * API 请求日志 — 记录所有接口调用（开发手册规范）
 *
 * 用途：性能分析、接口监控、流量分析
 */
class ApiLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);

        Log::channel('api')->info('API请求记录', [
            'url' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
            'status' => $response->status(),
            'duration_ms' => $duration,
        ]);

        return $response;
    }
}
class ApiLogMiddleware
{
    /**
     * 记录 API 请求日志
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $endTime = microtime(true);
        $duration = number_format(($endTime - $startTime) * 1000, 2);
        
        Log::channel('api')->info('API 请求日志', [
            'trace_id' => $request->attributes->get('trace_id'),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status' => $response->status(),
            'duration' => $duration . 'ms',
        ]);
        
        return $response;
    }
}
