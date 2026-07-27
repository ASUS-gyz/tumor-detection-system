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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->unsignedBigInteger('patient_id')->comment('患者ID');
            $table->unsignedBigInteger('doctor_id')->comment('医生ID');
            $table->date('appointment_date')->comment('预约日期');
            $table->string('appointment_time', 5)->comment('预约时段 HH:mm');
            $table->enum('status', ['pending', 'called', 'in_progress', 'completed', 'cancelled'])
                  ->default('pending')->comment('预约状态：pending-待接诊，called-已叫号，in_progress-接诊中，completed-已完成，cancelled-已取消');
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('users');
            $table->foreign('doctor_id')->references('id')->on('users');
            $table->index('appointment_date');
            $table->index('status');
            $table->index(['patient_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
