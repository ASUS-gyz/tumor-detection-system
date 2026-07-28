<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->unsignedBigInteger('user_id')->comment('接收用户ID');
            $table->string('type', 50)->comment('通知类型：appointment_call/stock_warning/prescription_ready/system');
            $table->string('title', 200)->comment('通知标题');
            $table->text('content')->comment('通知内容');
            $table->boolean('is_read')->default(false)->comment('是否已读');
            $table->string('reference_type', 50)->nullable()->comment('关联业务类型');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('关联业务ID');
            $table->timestamp('created_at')->useCurrent()->comment('通知时间');

            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['user_id', 'is_read']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
