<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;

/**
 * Sanctum Guard 的 User Provider
 *
 * 继承 EloquentUserProvider，增加 Tokenable 模型支持
 */
class SanctumUserProvider extends EloquentUserProvider
{
    //
}
