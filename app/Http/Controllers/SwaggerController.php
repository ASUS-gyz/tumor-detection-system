<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="肿瘤科智能检测门诊系统 API",
 *     version="1.0.0",
 *     description="肿瘤科智能检测门诊系统后端接口文档"
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="本地开发服务器"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class SwaggerController
{
    /**
     * @OA\Get(
     *     path="/",
     *     summary="API 健康检查",
     *     tags={"健康检查"},
     *     @OA\Response(
     *         response=200,
     *         description="健康状态正常"
     *     )
     * )
     */
    public function health()
    {
        return response()->json(['status' => 'ok']);
    }
}