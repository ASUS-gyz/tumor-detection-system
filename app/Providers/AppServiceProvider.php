<?php

namespace App\Providers;

use App\Auth\SanctumGuard;
use Illuminate\Auth\AuthManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 注册 sanctum 自定义 Guard
        $this->app->make(AuthManager::class)->extend('sanctum', function ($app, $name, array $config) {
            $provider = $app['auth']->createUserProvider($config['provider'] ?? null);

            return new SanctumGuard($provider, $app['request']);
        });
    }
}
