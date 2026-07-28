<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained('drugs')->onDelete('cascade')->comment('药品ID');
            $table->integer('quantity')->default(0)->comment('当前库存');
            $table->integer('min_stock')->default(10)->comment('最低库存预警');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_stocks');
    }
};