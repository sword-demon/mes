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
        Schema::create('mes_inventory_alerts', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('material_id')->comment('物料 ID')->constrained('mes_materials')->restrictOnDelete();
            $table->decimal('min_stock', 12, 3)->comment('触发时最低库存');
            $table->decimal('current_quantity', 12, 3)->comment('触发时当前库存');
            $table->string('status', 16)->comment('状态：pending/confirmed/closed');
            $table->foreignId('handled_by_id')->nullable()->comment('处理人 ID')->constrained('sys_users')->nullOnDelete();
            $table->unsignedTinyInteger('active_key')->nullable()->comment('未关闭预警唯一键');
            $table->timestamp('handled_at')->nullable()->comment('处理时间');
            $table->timestamp('resolved_at')->nullable()->comment('库存恢复时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index(['material_id', 'status', 'id'], 'mes_inventory_alerts_material_status_id_index');
            $table->index(['status', 'created_at'], 'mes_inventory_alerts_status_created_index');
            $table->unique(['material_id', 'active_key'], 'mes_inventory_alerts_material_active_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_inventory_alerts');
    }
};
