<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->unsignedBigInteger('user_id')->nullable()->comment('操作人ID');
            $table->string('user_name', 50)->nullable()->comment('操作人姓名');
            $table->string('action', 50)->comment('操作类型：create/update/delete/login/logout/status_change');
            $table->string('module', 50)->comment('操作模块：appointment/user/drug/ai_diagnosis/medical_record/prescription/system');
            $table->string('target_type', 50)->nullable()->comment('操作对象类型');
            $table->unsignedBigInteger('target_id')->nullable()->comment('操作对象ID');
            $table->text('content')->nullable()->comment('操作内容描述');
            $table->string('ip', 45)->nullable()->comment('操作IP');
            $table->timestamp('created_at')->useCurrent()->comment('操作时间');

            $table->index('user_id');
            $table->index('module');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
    }
};
