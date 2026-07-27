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
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->unsignedBigInteger('prescription_id')->comment('处方ID');
            $table->unsignedBigInteger('drug_id')->comment('药品ID');
            $table->unsignedInteger('quantity')->comment('数量');
            $table->string('dosage', 255)->comment('用量说明');
            $table->text('instructions')->nullable()->comment('用药说明');
            $table->timestamps();

            $table->foreign('prescription_id')->references('id')->on('prescriptions')->onDelete('cascade');
            $table->foreign('drug_id')->references('id')->on('drugs');
            $table->index('prescription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
