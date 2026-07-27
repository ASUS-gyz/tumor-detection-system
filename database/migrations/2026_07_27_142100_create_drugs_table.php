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
        Schema::create('drugs', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->string('name', 255)->unique()->comment('药品名称');
            $table->string('category', 100)->comment('药品分类');
            $table->string('specification', 100)->comment('规格');
            $table->string('unit', 20)->comment('单位');
            $table->unsignedInteger('stock_quantity')->default(0)->comment('当前库存数量');
            $table->decimal('price', 10, 2)->comment('单价（元）');
            $table->text('description')->nullable()->comment('药品说明');
            $table->timestamps();

            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drugs');
    }
};
