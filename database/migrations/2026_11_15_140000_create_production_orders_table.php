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
        Schema::create('mes_production_orders', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 64)->unique()->comment('生产订单号');
            $table->foreignId('product_id')->comment('产品 ID')->constrained('mes_products')->restrictOnDelete();
            $table->foreignId('product_version_id')->comment('产品版本 ID')->constrained('mes_product_versions')->restrictOnDelete();
            $table->foreignId('bom_id')->comment('锁定的 BOM ID')->constrained('mes_boms')->restrictOnDelete();
            $table->foreignId('route_id')->comment('锁定的工艺路线 ID')->constrained('mes_routes')->restrictOnDelete();
            $table->foreignId('production_line_id')->comment('生产产线 ID')->constrained('mes_production_lines')->restrictOnDelete();
            $table->string('bom_version', 32)->comment('创建时的 BOM 版本快照');
            $table->string('route_version', 32)->comment('创建时的工艺路线版本快照');
            $table->decimal('planned_quantity', 12, 3)->comment('计划数量');
            $table->decimal('produced_quantity', 12, 3)->default(0)->comment('已生产数量');
            $table->decimal('qualified_quantity', 12, 3)->default(0)->comment('合格数量');
            $table->decimal('defective_quantity', 12, 3)->default(0)->comment('不良数量');
            $table->timestamp('planned_start_at')->comment('计划开始时间');
            $table->timestamp('planned_end_at')->nullable()->comment('计划结束时间');
            $table->string('status', 16)->default('draft')->comment('状态：草稿/待审核/已审核/已下达/生产中/已暂停/已完成/已关闭/已取消');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index(['status', 'planned_start_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_production_orders');
    }
};
