<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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