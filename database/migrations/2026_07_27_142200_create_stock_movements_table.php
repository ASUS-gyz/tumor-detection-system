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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->unsignedBigInteger('drug_id')->comment('药品ID');
            $table->enum('type', ['in', 'out'])->comment('变动类型：in-入库，out-出库');
            $table->unsignedInteger('quantity')->comment('变动数量');
            $table->unsignedInteger('before_quantity')->comment('变动前库存');
            $table->unsignedInteger('after_quantity')->comment('变动后库存');
            $table->string('reference_type', 50)->comment('关联业务类型：manual_stock_in / prescription_dispense');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('关联业务ID');
            $table->text('remark')->nullable()->comment('备注');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人ID');
            $table->timestamp('created_at')->useCurrent()->comment('变动时间');

            $table->foreign('drug_id')->references('id')->on('drugs');
            $table->foreign('operator_id')->references('id')->on('users');
            $table->index('drug_id');
            $table->index('type');
            $table->index('created_at');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
