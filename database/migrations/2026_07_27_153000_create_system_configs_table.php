<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_configs', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->string('key', 100)->unique()->comment('配置键名');
            $table->string('value', 500)->comment('配置值');
            $table->string('description', 255)->nullable()->comment('配置说明');
            $table->string('group', 50)->default('system')->comment('配置分组：system/ai/appointment');
            $table->timestamps();

            $table->index('group');
        });

        // 初始化默认配置
        DB::table('system_configs')->insert([
            ['key' => 'stock_low_threshold', 'value' => '10', 'description' => '库存预警阈值', 'group' => 'drug'],
            ['key' => 'appointment_time_slots', 'value' => '08:30,09:15,10:00,10:45,13:30,14:15,15:00,15:45', 'description' => '预约时段', 'group' => 'appointment'],
            ['key' => 'ai_diagnosis_mode', 'value' => 'mock', 'description' => 'AI诊断模式：mock/remote', 'group' => 'ai'],
            ['key' => 'max_daily_appointments', 'value' => '20', 'description' => '医生每日最大接诊数', 'group' => 'appointment'],
            ['key' => 'system_name', 'value' => '肿瘤科智能检测门诊系统', 'description' => '系统名称', 'group' => 'system'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_configs');
    }
};
