<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\TraceIdMiddleware::class);
        $middleware->api(append: [
            \App\Http\Middleware\ApiLogMiddleware::class,
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $_request) => true,
        );
        $exceptions->render(
            fn (\Illuminate\Validation\ValidationException $e) => $e->response
                ?? \App\Support\Result::error(
                    \App\Enums\ResponseCode::PARAM_ERROR,
                    collect($e->errors())->flatten()->first()
                )
        );
        $exceptions->render(
            fn (\Illuminate\Auth\AuthenticationException $_e) => \App\Support\Result::error(
                \App\Enums\ResponseCode::UNAUTHORIZED
            )
        );
        $exceptions->render(
            fn (\Illuminate\Database\Eloquent\ModelNotFoundException $_e) => \App\Support\Result::error(
                \App\Enums\ResponseCode::DATA_NOT_FOUND
            )
        );
        $exceptions->render(
            fn (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $_e) => \App\Support\Result::error(
                \App\Enums\ResponseCode::DATA_NOT_FOUND,
                '接口不存在'
            )
        );
        $exceptions->render(
            fn (\App\Exceptions\BusinessException $e) => \App\Support\Result::error(
                $e->codeEnum,
                $e->getMessage()
            )
        );
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