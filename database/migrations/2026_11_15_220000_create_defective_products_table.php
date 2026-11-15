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
        Schema::create('mes_defective_products', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('production_order_id')->comment('生产订单 ID')->constrained('mes_production_orders')->restrictOnDelete();
            $table->foreignId('route_operation_id')->comment('工序 ID')->constrained('mes_route_operations')->restrictOnDelete();
            $table->foreignId('production_batch_id')->comment('生产批次 ID')->constrained('mes_production_batches')->restrictOnDelete();
            $table->foreignId('defect_code_id')->comment('缺陷代码 ID')->constrained('mes_defect_codes')->restrictOnDelete();
            $table->decimal('quantity', 12, 3)->comment('不良数量');
            $table->string('remark', 255)->nullable()->comment('登记备注');
            $table->string('status', 16)->default('pending')->comment('状态：pending 待处理 / processing 处理中 / pending_recheck 待复检 / closed 已关闭');
            $table->foreignId('handled_by_id')->nullable()->comment('最近处理人用户 ID')->constrained('sys_users')->restrictOnDelete();
            $table->string('scrap_reason', 255)->nullable()->comment('报废原因');
            $table->decimal('scrap_quantity', 12, 3)->nullable()->comment('报废数量');
            $table->timestamp('scrapped_at')->nullable()->comment('报废时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index(['production_order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_defective_products');
    }
};
