<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 全局中间件 — TraceId 链路追踪
        $middleware->append(\App\Http\Middleware\TraceIdMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 对所有请求统一返回 JSON（开发手册规范）
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => true,
        );

        // 参数验证异常
        $exceptions->render(
            fn (\Illuminate\Validation\ValidationException $e) => \App\Support\Result::error(
                \App\Enums\ResponseCode::PARAM_ERROR,
                collect($e->errors())->flatten()->first()
            )
        );

        // 未登录
        $exceptions->render(
            fn (\Illuminate\Auth\AuthenticationException $e) => \App\Support\Result::error(
                \App\Enums\ResponseCode::UNAUTHORIZED
            )
        );

        // 模型不存在
        $exceptions->render(
            fn (\Illuminate\Database\Eloquent\ModelNotFoundException $e) => \App\Support\Result::error(
                \App\Enums\ResponseCode::DATA_NOT_FOUND
            )
        );

        // 路由不存在
        $exceptions->render(
            fn (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) => \App\Support\Result::error(
                \App\Enums\ResponseCode::DATA_NOT_FOUND,
                '接口不存在'
            )
        );

        // 业务异常
        $exceptions->render(
            fn (\App\Exceptions\BusinessException $e) => \App\Support\Result::error(
                $e->codeEnum,
                $e->getMessage()
            )
        );

        // 数据库异常
        $exceptions->render(function (\Illuminate\Database\QueryException $e, Request $request) {
            Log::channel('exception')->error('数据库异常', [
                'trace_id' => $request->attributes->get('trace_id'),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'message' => $e->getMessage(),
            ]);

            return \App\Support\Result::error(
                \App\Enums\ResponseCode::DATABASE_ERROR
            );
        });

        // 未知异常 — 兜底处理
        $exceptions->render(function (\Throwable $e, Request $request) {
            Log::channel('exception')->error($e->getMessage(), [
                'trace_id' => $request->attributes->get('trace_id'),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return \App\Support\Result::error(
                \App\Enums\ResponseCode::SYSTEM_ERROR
            );
        });
    })->create();
