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
        Schema::create('mes_production_batches', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 80)->unique()->comment('生产批次号');
            $table->foreignId('production_order_id')->comment('生产订单 ID')->constrained('mes_production_orders')->restrictOnDelete();
            $table->unsignedInteger('quantity')->comment('批次数量');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_production_batches');
    }
};
