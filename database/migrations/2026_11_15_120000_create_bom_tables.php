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
        Schema::create('mes_boms', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 64)->unique()->comment('BOM 编码');
            $table->foreignId('product_version_id')->comment('产品版本 ID')->constrained('mes_product_versions')->restrictOnDelete();
            $table->string('version', 32)->comment('BOM 版本号');
            $table->string('audit_status', 16)->default('draft')->comment('审核状态：草稿/待审核/已审核');
            $table->timestamp('audited_at')->nullable()->comment('审核通过时间');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(['product_version_id', 'version']);
        });

        Schema::create('mes_bom_items', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('bom_id')->comment('BOM ID')->constrained('mes_boms')->cascadeOnDelete();
            $table->foreignId('material_id')->comment('物料 ID')->constrained('mes_materials')->restrictOnDelete();
            $table->decimal('quantity', 12, 3)->comment('单位用量');
            $table->decimal('loss_rate', 5, 2)->default(0)->comment('损耗率（%）');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(['bom_id', 'material_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_bom_items');
        Schema::dropIfExists('mes_boms');
    }
};
