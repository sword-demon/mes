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
        Schema::create('mes_process_records', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('production_task_id')->comment('生产任务 ID')->constrained('mes_production_tasks')->restrictOnDelete();
            $table->foreignId('route_operation_id')->comment('工艺路线工序 ID')->constrained('mes_route_operations')->restrictOnDelete();
            $table->unsignedInteger('sequence')->comment('工序顺序');
            $table->string('sn', 64)->nullable()->comment('产品 SN（SN 台账建立后关联）');
            $table->foreignId('employee_id')->nullable()->comment('操作人员档案 ID')->constrained('mes_employees')->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->comment('操作班组 ID')->constrained('mes_teams')->nullOnDelete();
            $table->unsignedBigInteger('equipment_id')->nullable()->comment('设备 ID（设备台账建立后补外键）');
            $table->string('status', 20)->default('pending')->comment('状态：待加工/加工中/待检验/检验通过/待流转/已流转/返工中');
            $table->timestamp('started_at')->nullable()->comment('开始加工时间');
            $table->timestamp('completed_at')->nullable()->comment('加工完成时间');
            $table->timestamp('inspected_at')->nullable()->comment('检验通过时间');
            $table->timestamp('submitted_at')->nullable()->comment('提交流转时间');
            $table->timestamp('received_at')->nullable()->comment('接收流转时间');
            $table->string('exception_reason', 255)->nullable()->comment('工序异常原因');
            $table->timestamp('exception_at')->nullable()->comment('工序异常时间');
            $table->string('rework_reason', 255)->nullable()->comment('返工原因');
            $table->timestamp('rework_at')->nullable()->comment('返工时间');
            $table->unsignedSmallInteger('rework_count')->default(0)->comment('返工次数');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(['production_task_id', 'route_operation_id'], 'mes_process_records_task_operation_unique');
            $table->index(['production_task_id', 'sequence']);
            $table->index('status');
            $table->index('sn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_process_records');
    }
};
