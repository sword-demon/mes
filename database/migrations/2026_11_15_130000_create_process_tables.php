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
        Schema::create('mes_processes', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 64)->unique()->comment('工序编码');
            $table->string('name', 64)->comment('工序名称');
            $table->string('type', 16)->comment('工序类型');
            $table->decimal('standard_hours', 8, 2)->default(0)->comment('标准工时（小时）');
            $table->boolean('requires_inspection')->default(false)->comment('是否需要检验');
            $table->string('description', 255)->nullable()->comment('工序说明');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });

        Schema::create('mes_routes', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 64)->unique()->comment('工艺路线编码');
            $table->foreignId('product_id')->comment('产品 ID')->constrained('mes_products')->restrictOnDelete();
            $table->string('version', 32)->comment('工艺路线版本号');
            $table->string('audit_status', 16)->default('draft')->comment('审核状态：草稿/待审核/已审核');
            $table->timestamp('audited_at')->nullable()->comment('审核通过时间');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(['product_id', 'version']);
        });

        Schema::create('mes_route_operations', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('route_id')->comment('工艺路线 ID')->constrained('mes_routes')->cascadeOnDelete();
            $table->foreignId('process_id')->comment('工序 ID')->constrained('mes_processes')->restrictOnDelete();
            $table->unsignedInteger('sequence')->comment('工序顺序');
            $table->decimal('standard_hours', 8, 2)->default(0)->comment('标准工时（小时）');
            $table->string('parameters', 255)->nullable()->comment('工序参数');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(['route_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_route_operations');
        Schema::dropIfExists('mes_routes');
        Schema::dropIfExists('mes_processes');
    }
};
