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
        Schema::table('mes_process_records', function (Blueprint $table) {
            $table->unsignedInteger('inspection_cycle')->default(0)->after('rework_count')->comment('检验轮次（每次完成加工进入待检验递增）');
        });

        Schema::table('mes_inspection_tasks', function (Blueprint $table) {
            $table->unsignedInteger('inspection_cycle')->default(0)->after('route_operation_id')->comment('对应工序检验轮次（OQC 为 0）');
            $table->index(['process_record_id', 'inspection_cycle', 'result'], 'mes_inspection_tasks_cycle_index');
        });

        Schema::create('mes_inspection_task_items', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('inspection_task_id')->comment('检验任务 ID')->constrained('mes_inspection_tasks')->cascadeOnDelete();
            $table->foreignId('inspection_item_id')->comment('检验项目 ID')->constrained('mes_inspection_items')->restrictOnDelete();
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(['inspection_task_id', 'inspection_item_id'], 'mes_inspection_task_items_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_inspection_task_items');

        Schema::table('mes_inspection_tasks', function (Blueprint $table) {
            $table->dropIndex('mes_inspection_tasks_cycle_index');
            $table->dropColumn('inspection_cycle');
        });

        Schema::table('mes_process_records', function (Blueprint $table) {
            $table->dropColumn('inspection_cycle');
        });
    }
};
