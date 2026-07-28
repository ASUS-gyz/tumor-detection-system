<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->unsignedBigInteger('doctor_id')->comment('医生ID');
            $table->unsignedTinyInteger('day_of_week')->comment('星期（0=周日,1=周一...6=周六）');
            $table->boolean('is_available')->default(true)->comment('是否接诊');
            $table->json('time_slots')->nullable()->comment('可预约时段数组，如["08:30","09:15"]');
            $table->unsignedInteger('max_patients')->default(20)->comment('当日最大接诊数');
            $table->timestamps();

            $table->foreign('doctor_id')->references('id')->on('users');
            $table->unique(['doctor_id', 'day_of_week']);
            $table->index('doctor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_schedules');
    }
};
