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
        Schema::create('mes_material_returns', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 64)->unique()->comment('退料单号');
            $table->foreignId('requisition_id')->comment('原领料单 ID')->constrained('mes_material_requisitions')->restrictOnDelete();
            $table->foreignId('production_order_id')->comment('生产订单 ID')->constrained('mes_production_orders')->restrictOnDelete();
            $table->string('status', 16)->default('pending')->comment('状态：pending 待审核/approved 已审核');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->foreignId('created_by_id')->comment('创建人用户 ID')->constrained('sys_users')->restrictOnDelete();
            $table->foreignId('approved_by_id')->nullable()->comment('审核人用户 ID')->constrained('sys_users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable()->comment('审核时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index(['production_order_id', 'created_at'], 'mes_material_returns_order_created_index');
            $table->index('status', 'mes_material_returns_status_index');
        });

        Schema::create('mes_material_return_items', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('material_return_id')->comment('退料单 ID')->constrained('mes_material_returns')->cascadeOnDelete();
            $table->foreignId('requisition_item_id')->comment('原领料明细 ID')->constrained('mes_material_requisition_items')->restrictOnDelete();
            $table->decimal('quantity', 12, 3)->comment('退料数量');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(['material_return_id', 'requisition_item_id'], 'mes_material_return_items_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_material_return_items');
        Schema::dropIfExists('mes_material_returns');
    }
};
