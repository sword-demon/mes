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
        Schema::create('mes_inspection_tasks', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('production_order_id')->comment('生产订单 ID')->constrained('mes_production_orders')->restrictOnDelete();
            $table->foreignId('process_record_id')->nullable()->comment('工序执行记录 ID（IPQC 必填，返工后可复检）')->constrained('mes_process_records')->restrictOnDelete();
            $table->string('inspection_type', 8)->comment('检验类型：ipqc 过程检验 / oqc 出货检验');
            $table->string('result', 8)->nullable()->comment('判定结果：passed 合格 / failed 不合格，空为待检验');
            $table->foreignId('inspector_id')->nullable()->comment('检验员用户 ID')->constrained('sys_users')->restrictOnDelete();
            $table->timestamp('inspected_at')->nullable()->comment('检验时间');
            $table->string('remark', 255)->nullable()->comment('检验备注');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index(['production_order_id', 'inspection_type']);
            $table->index('result');
            $table->index('inspected_at');
        });

        Schema::create('mes_inspection_results', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('inspection_task_id')->comment('检验任务 ID')->constrained('mes_inspection_tasks')->cascadeOnDelete();
            $table->foreignId('inspection_item_id')->comment('检验项目 ID')->constrained('mes_inspection_items')->restrictOnDelete();
            $table->decimal('measured_value', 12, 3)->comment('实测值');
            $table->decimal('standard_value', 12, 3)->comment('判定时标准值快照');
            $table->decimal('min_value', 12, 3)->comment('判定时最小允许值快照');
            $table->decimal('max_value', 12, 3)->comment('判定时最大允许值快照');
            $table->boolean('passed')->comment('单项是否合格');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(['inspection_task_id', 'inspection_item_id'], 'mes_inspection_results_task_item_unique');
        });

        Schema::create('mes_inspection_images', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('inspection_task_id')->comment('检验任务 ID')->constrained('mes_inspection_tasks')->cascadeOnDelete();
            $table->string('path', 255)->comment('存储路径');
            $table->string('original_name', 255)->nullable()->comment('原始文件名');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_inspection_images');
        Schema::dropIfExists('mes_inspection_results');
        Schema::dropIfExists('mes_inspection_tasks');
    }
};
