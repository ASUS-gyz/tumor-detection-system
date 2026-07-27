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
        Schema::create('ai_diagnoses', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->enum('type', ['text', 'image'])->comment('诊断类型：text-文字诊断，image-图文诊断');
            $table->unsignedBigInteger('patient_id')->comment('患者ID');
            $table->unsignedBigInteger('doctor_id')->nullable()->comment('医生ID（图文诊断时有值）');
            $table->unsignedBigInteger('appointment_id')->nullable()->comment('关联预约ID');
            // 文字诊断字段（type=text）
            $table->text('symptom_description')->nullable()->comment('文字诊断：原始症状描述');
            $table->text('analysis')->nullable()->comment('文字诊断：病情分析');
            $table->string('risk_level', 20)->nullable()->comment('文字诊断：风险等级（低风险/中风险/高风险）');
            $table->text('risk_warning')->nullable()->comment('文字诊断：风险提示');
            $table->text('advice')->nullable()->comment('文字诊断：就诊建议');
            $table->json('possible_conditions')->nullable()->comment('文字诊断：可能情况列表');
            // 图文诊断字段（type=image）
            $table->text('description')->nullable()->comment('图文诊断：病情文字描述');
            $table->text('imaging_features')->nullable()->comment('图文诊断：影像特征分析');
            $table->string('risk_assessment', 20)->nullable()->comment('图文诊断：风险评估（低风险/中风险/高风险）');
            $table->text('suspected_lesions')->nullable()->comment('图文诊断：疑似病灶分析');
            $table->text('treatment_recommendations')->nullable()->comment('图文诊断：专业诊疗建议');
            $table->string('confidence', 10)->nullable()->comment('图文诊断：AI置信度');
            $table->string('image_url', 255)->nullable()->comment('图文诊断：CT影像图片地址');
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('users');
            $table->foreign('doctor_id')->references('id')->on('users');
            $table->foreign('appointment_id')->references('id')->on('appointments');
            $table->index('type');
            $table->index('patient_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_diagnoses');
    }
};
