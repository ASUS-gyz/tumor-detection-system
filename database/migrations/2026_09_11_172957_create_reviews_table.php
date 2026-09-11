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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->unsignedBigInteger('appointment_id')->comment('关联预约ID（一预约一评价）');
            $table->unsignedBigInteger('patient_id')->comment('评价患者ID');
            $table->unsignedBigInteger('doctor_id')->comment('被评价医生ID');
            $table->unsignedTinyInteger('rating')->comment('评分1-5');
            $table->string('content', 500)->nullable()->comment('评价内容');
            $table->timestamps();

            $table->foreign('appointment_id')->references('id')->on('appointments');
            $table->foreign('patient_id')->references('id')->on('users');
            $table->foreign('doctor_id')->references('id')->on('users');
            $table->unique('appointment_id');
            $table->index('doctor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
