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
        Schema::create('mes_materials', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 64)->unique()->comment('物料编码');
            $table->string('name', 128)->comment('物料名称');
            $table->string('type', 32)->comment('物料类型');
            $table->string('specification', 255)->nullable()->comment('规格型号');
            $table->string('unit', 16)->comment('计量单位');
            $table->decimal('min_stock', 12, 3)->default(0)->comment('最低库存');
            $table->decimal('safety_stock', 12, 3)->default(0)->comment('安全库存');
            $table->boolean('batch_managed')->default(false)->comment('是否批次管理');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_materials');
    }
};
