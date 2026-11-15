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
        Schema::create('mes_production_reports', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 80)->unique()->comment('报工单号');
            $table->foreignId('production_task_id')->comment('生产任务 ID')->constrained('mes_production_tasks')->restrictOnDelete();
            $table->foreignId('route_operation_id')->comment('工艺路线工序 ID')->constrained('mes_route_operations')->restrictOnDelete();
            $table->foreignId('employee_id')->comment('作业人员档案 ID')->constrained('mes_employees')->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->comment('作业班组 ID')->constrained('mes_teams')->nullOnDelete();
            $table->decimal('quantity', 12, 3)->comment('报工数量');
            $table->decimal('qualified_quantity', 12, 3)->comment('合格数量');
            $table->decimal('defective_quantity', 12, 3)->comment('不良数量');
            $table->string('type', 20)->default('normal')->comment('报工类型：normal 正常 / batch 批量 / reversed 已冲销');
            $table->timestamp('reported_at')->comment('报工时间');
            $table->timestamp('reversed_at')->nullable()->comment('冲销时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index(['production_task_id', 'reported_at']);
            $table->index(['employee_id', 'reported_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_production_reports');
    }
};
