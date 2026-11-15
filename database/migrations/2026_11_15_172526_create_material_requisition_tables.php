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
        Schema::create('mes_material_requisitions', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 64)->unique()->comment('领料单号');
            $table->foreignId('production_order_id')->comment('生产订单 ID')->constrained('mes_production_orders')->restrictOnDelete();
            $table->string('status', 16)->default('pending')->comment('状态：待发料/已发料');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->foreignId('created_by_id')->comment('创建人用户 ID')->constrained('sys_users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index(['production_order_id', 'created_at'], 'mes_material_requisitions_order_created_index');
            $table->index('status', 'mes_material_requisitions_status_index');
        });

        Schema::create('mes_material_requisition_items', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('requisition_id')->comment('领料单 ID')->constrained('mes_material_requisitions')->cascadeOnDelete();
            $table->foreignId('material_id')->comment('物料 ID')->constrained('mes_materials')->restrictOnDelete();
            $table->decimal('requested_quantity', 12, 3)->comment('申请领用量');
            $table->decimal('issued_quantity', 12, 3)->default(0)->comment('已发料量');
            $table->boolean('over_requirement')->default(false)->comment('累计发料是否超过 BOM 需求');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(['requisition_id', 'material_id'], 'mes_material_requisition_items_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_material_requisition_items');
        Schema::dropIfExists('mes_material_requisitions');
    }
};
