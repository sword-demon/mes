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
        Schema::create('mes_product_sns', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('sn', 32)->unique()->comment('产品序列号');
            $table->foreignId('production_order_id')->comment('生产订单 ID')->constrained('mes_production_orders')->restrictOnDelete();
            $table->foreignId('production_batch_id')->comment('生产批次 ID')->constrained('mes_production_batches')->restrictOnDelete();
            $table->string('material_batch_no', 64)->nullable()->comment('物料批次号（物料批次台账建立后关联）');
            $table->unsignedBigInteger('equipment_id')->nullable()->comment('设备 ID（设备台账建立后补外键）');
            $table->foreignId('employee_id')->nullable()->comment('绑定操作人员 ID')->constrained('mes_employees')->nullOnDelete();
            $table->string('status', 20)->default('active')->comment('状态：active 在用 / voided 已作废');
            $table->timestamp('printed_at')->nullable()->comment('标签最近打印时间');
            $table->timestamp('voided_at')->nullable()->comment('作废时间');
            $table->string('void_reason', 255)->nullable()->comment('作废原因');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_product_sns');
    }
};
