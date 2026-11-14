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
        Schema::create('mes_products', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 64)->unique()->comment('产品编码');
            $table->string('name', 128)->comment('产品名称');
            $table->string('model', 64)->nullable()->comment('型号');
            $table->string('specification', 255)->nullable()->comment('规格');
            $table->string('category', 64)->comment('分类');
            $table->string('unit', 16)->comment('单位');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });

        Schema::create('mes_product_versions', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('product_id')->comment('产品 ID')->constrained('mes_products')->restrictOnDelete();
            $table->string('version', 32)->comment('版本号');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(['product_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_product_versions');
        Schema::dropIfExists('mes_products');
    }
};
