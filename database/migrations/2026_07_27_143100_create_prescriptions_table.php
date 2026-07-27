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
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->unsignedBigInteger('appointment_id')->comment('关联预约ID');
            $table->unsignedBigInteger('patient_id')->comment('患者ID');
            $table->unsignedBigInteger('doctor_id')->comment('开具医生ID');
            $table->enum('status', ['pending', 'dispensed'])->default('pending')->comment('处方状态：pending-待取药，dispensed-已取药');
            $table->timestamps();

            $table->foreign('appointment_id')->references('id')->on('appointments');
            $table->foreign('patient_id')->references('id')->on('users');
            $table->foreign('doctor_id')->references('id')->on('users');
            $table->index('status');
            $table->index('patient_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
