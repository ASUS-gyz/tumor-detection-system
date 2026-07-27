<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->string('name', 50)->comment('姓名');
            $table->string('email', 255)->unique()->comment('邮箱');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255)->comment('密码');
            $table->enum('role', ['patient', 'doctor', 'admin'])->comment('角色');
            $table->string('phone', 20)->nullable()->comment('手机号');
            $table->string('avatar_url', 255)->nullable()->comment('头像地址');
            $table->enum('status', ['active', 'disabled'])->default('active')->comment('账号状态');
            $table->string('title', 50)->nullable()->comment('职称（仅doctor）');
            $table->string('specialty', 255)->nullable()->comment('专长（仅doctor）');
            $table->string('department', 100)->nullable()->comment('科室（仅doctor）');
            $table->text('introduction')->nullable()->comment('个人简介（仅doctor）');
            $table->unsignedInteger('experience_years')->nullable()->comment('从业年限（仅doctor）');
            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
            $table->index('status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
