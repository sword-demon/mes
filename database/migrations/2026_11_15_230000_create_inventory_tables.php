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
        Schema::create('mes_warehouses', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 32)->unique()->comment('仓库编码');
            $table->string('name', 64)->comment('仓库名称');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });

        Schema::create('mes_storage_locations', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('warehouse_id')->comment('仓库 ID')->constrained('mes_warehouses')->restrictOnDelete();
            $table->string('code', 32)->comment('库位编码');
            $table->string('name', 64)->comment('库位名称');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(['warehouse_id', 'code'], 'mes_storage_locations_warehouse_code_unique');
        });

        Schema::create('mes_inventories', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('material_id')->comment('物料 ID')->constrained('mes_materials')->restrictOnDelete();
            $table->foreignId('location_id')->comment('库位 ID')->constrained('mes_storage_locations')->restrictOnDelete();
            $table->string('batch_no', 80)->default('')->comment('批次号，非批次物料为空串');
            $table->decimal('current_quantity', 12, 3)->default(0)->comment('当前库存');
            $table->decimal('frozen_quantity', 12, 3)->default(0)->comment('冻结库存');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(['material_id', 'location_id', 'batch_no'], 'mes_inventories_position_unique');
            $table->index('batch_no', 'mes_inventories_batch_no_index');
        });

        Schema::create('mes_inventory_transactions', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('inventory_id')->comment('库存 ID')->constrained('mes_inventories')->restrictOnDelete();
            $table->foreignId('material_id')->comment('物料 ID')->constrained('mes_materials')->restrictOnDelete();
            $table->string('business_type', 32)->comment('业务类型：inbound/outbound/adjustment');
            $table->decimal('quantity', 12, 3)->comment('变动数量，入库为正、出库为负');
            $table->decimal('before_quantity', 12, 3)->comment('变动前数量');
            $table->decimal('after_quantity', 12, 3)->comment('变动后数量');
            $table->string('reference_no', 80)->nullable()->comment('关联单据号');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index(['inventory_id', 'created_at'], 'mes_inventory_transactions_inventory_created_index');
            $table->index('business_type', 'mes_inventory_transactions_business_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_inventory_transactions');
        Schema::dropIfExists('mes_inventories');
        Schema::dropIfExists('mes_storage_locations');
        Schema::dropIfExists('mes_warehouses');
    }
};
