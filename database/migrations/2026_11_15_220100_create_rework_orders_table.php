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
        Schema::create('mes_rework_orders', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 64)->unique()->comment('返工单号');
            $table->foreignId('defective_product_id')->comment('不良品 ID')->constrained('mes_defective_products')->cascadeOnDelete();
            $table->string('reason', 255)->comment('返工原因');
            $table->foreignId('created_by_id')->comment('创建人用户 ID')->constrained('sys_users')->restrictOnDelete();
            $table->string('recheck_result', 8)->nullable()->comment('复检结果：passed 合格 / failed 不合格');
            $table->string('recheck_remark', 255)->nullable()->comment('复检备注');
            $table->foreignId('rechecked_by_id')->nullable()->comment('复检人用户 ID')->constrained('sys_users')->restrictOnDelete();
            $table->timestamp('rechecked_at')->nullable()->comment('复检时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_rework_orders');
    }
};
