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
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->unsignedBigInteger('appointment_id')->unique()->comment('关联预约ID（一对一）');
            $table->unsignedBigInteger('patient_id')->comment('患者ID');
            $table->unsignedBigInteger('doctor_id')->comment('医生ID');
            $table->text('symptoms')->comment('症状描述');
            $table->text('imaging_findings')->nullable()->comment('影像检查情况');
            $table->text('preliminary_diagnosis')->comment('初步诊断');
            $table->text('treatment_plan')->comment('诊疗医嘱');
            $table->timestamps();

            $table->foreign('appointment_id')->references('id')->on('appointments');
            $table->foreign('patient_id')->references('id')->on('users');
            $table->foreign('doctor_id')->references('id')->on('users');
            $table->index('patient_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
