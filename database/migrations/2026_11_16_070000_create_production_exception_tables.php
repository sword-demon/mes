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
        Schema::create('mes_production_exceptions', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 40)->unique()->comment('异常编号');
            $table->string('type', 20)->comment('异常类型：equipment/material/process/quality/personnel');
            $table->string('status', 20)->default('reported')->comment('状态：reported/processing/closed');
            $table->foreignId('production_order_id')->nullable()->comment('关联生产订单 ID')->constrained('mes_production_orders')->restrictOnDelete();
            $table->foreignId('process_record_id')->nullable()->comment('关联工序执行记录 ID')->constrained('mes_process_records')->nullOnDelete();
            $table->text('description')->comment('异常描述');
            $table->foreignId('reported_by_id')->nullable()->comment('上报人 ID')->constrained('sys_users')->nullOnDelete();
            $table->foreignId('handled_by_id')->nullable()->comment('处理人 ID')->constrained('sys_users')->nullOnDelete();
            $table->text('handling_result')->nullable()->comment('处理结果');
            $table->dateTime('reported_at', 6)->comment('上报时间（UTC）');
            $table->timestamp('handled_at')->nullable()->comment('处理时间');
            $table->timestamp('closed_at')->nullable()->comment('关闭时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index(['type', 'status', 'reported_at'], 'mes_production_exceptions_type_status_time_index');
            $table->index(['production_order_id', 'status'], 'mes_production_exceptions_order_status_index');
        });

        Schema::create('mes_production_exception_images', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('production_exception_id')->comment('生产异常 ID')->constrained('mes_production_exceptions')->cascadeOnDelete();
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
        Schema::dropIfExists('mes_production_exception_images');
        Schema::dropIfExists('mes_production_exceptions');
    }
};
