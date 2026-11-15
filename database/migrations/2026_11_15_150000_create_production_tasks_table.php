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
        Schema::create('mes_production_tasks', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 80)->unique()->comment('生产任务编号');
            $table->foreignId('production_order_id')->comment('生产订单 ID')->constrained('mes_production_orders')->restrictOnDelete();
            $table->foreignId('product_id')->comment('产品 ID')->constrained('mes_products')->restrictOnDelete();
            $table->foreignId('route_id')->comment('工艺路线 ID')->constrained('mes_routes')->restrictOnDelete();
            $table->foreignId('production_line_id')->nullable()->comment('生产产线 ID')->constrained('mes_production_lines')->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->comment('生产班组 ID')->constrained('mes_teams')->restrictOnDelete();
            $table->foreignId('owner_id')->nullable()->comment('负责人员工 ID')->constrained('mes_employees')->restrictOnDelete();
            $table->decimal('planned_quantity', 12, 3)->comment('计划数量');
            $table->decimal('produced_quantity', 12, 3)->default(0)->comment('已生产数量');
            $table->decimal('qualified_quantity', 12, 3)->default(0)->comment('合格数量');
            $table->decimal('defective_quantity', 12, 3)->default(0)->comment('不良数量');
            $table->timestamp('planned_start_at')->comment('计划开始时间');
            $table->timestamp('planned_end_at')->nullable()->comment('计划结束时间');
            $table->string('status', 16)->default('pending')->comment('状态：待分配/生产中/已暂停/已完成');
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
        Schema::dropIfExists('mes_production_tasks');
    }
};
