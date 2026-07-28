<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * API 请求日志 — 记录所有接口调用
 *
 * 用途：性能分析、接口监控、流量分析
 */
class ApiLogMiddleware
{
    /**
     * 记录 API 请求日志
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = number_format((microtime(true) - $startTime) * 1000, 2) . 'ms';

        Log::channel('api')->info('API 请求日志', [
            'trace_id' => $request->attributes->get('trace_id'),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status' => $response->status(),
            'duration' => $duration,
        ]);

        return $response;
    }
}
