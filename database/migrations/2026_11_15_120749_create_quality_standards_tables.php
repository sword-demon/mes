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
        Schema::create('mes_inspection_items', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 64)->unique()->comment('检验项目编码');
            $table->string('name', 128)->comment('检验项目名称');
            $table->string('unit', 16)->comment('计量单位');
            $table->boolean('status')->default(true)->comment('是否启用');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
        Schema::create('mes_inspection_standards', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('inspection_item_id')->unique()->comment('检验项目 ID')->constrained('mes_inspection_items')->restrictOnDelete();
            $table->decimal('standard_value', 12, 3)->comment('标准值');
            $table->decimal('min_value', 12, 3)->comment('最小值');
            $table->decimal('max_value', 12, 3)->comment('最大值');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
        Schema::create('mes_defect_codes', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 64)->unique()->comment('缺陷编码');
            $table->string('name', 128)->comment('缺陷名称');
            $table->string('category', 64)->comment('缺陷分类');
            $table->boolean('status')->default(true)->comment('是否启用');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_defect_codes');
        Schema::dropIfExists('mes_inspection_standards');
        Schema::dropIfExists('mes_inspection_items');
    }
};
