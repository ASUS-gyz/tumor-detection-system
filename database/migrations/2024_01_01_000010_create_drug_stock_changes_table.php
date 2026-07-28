<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_stock_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained('drugs')->onDelete('cascade')->comment('药品ID');
            $table->enum('type', ['in', 'out'])->comment('变动类型：入库/出库');
            $table->integer('quantity')->comment('变动数量');
            $table->integer('before_quantity')->comment('变动前数量');
            $table->integer('after_quantity')->comment('变动后数量');
            $table->string('reason')->nullable()->comment('变动原因');
            $table->foreignId('related_id')->nullable()->comment('关联单据ID（处方ID等）');
            $table->string('related_type')->nullable()->comment('关联单据类型');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_stock_changes');
    }
};