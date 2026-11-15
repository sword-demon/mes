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
        Schema::create('mes_dispatch_orders', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 80)->unique()->comment('派工单号');
            $table->foreignId('production_task_id')->comment('生产任务 ID')->constrained('mes_production_tasks')->restrictOnDelete();
            $table->foreignId('route_operation_id')->comment('工艺路线工序 ID')->constrained('mes_route_operations')->restrictOnDelete();
            $table->foreignId('employee_id')->comment('作业人员档案 ID')->constrained('mes_employees')->restrictOnDelete();
            $table->foreignId('team_id')->comment('作业班组 ID')->constrained('mes_teams')->restrictOnDelete();
            $table->timestamp('dispatched_at')->comment('派工时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(
                ['production_task_id', 'route_operation_id', 'employee_id'],
                'mes_dispatch_orders_assignment_unique',
            );
            $table->index(['employee_id', 'dispatched_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_dispatch_orders');
    }
};
