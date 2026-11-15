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
        Schema::create('mes_equipments', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 64)->unique()->comment('设备编码');
            $table->string('name', 128)->comment('设备名称');
            $table->string('type', 32)->comment('设备类型');
            $table->foreignId('workshop_id')->comment('所属车间 ID')->constrained('mes_workshops')->restrictOnDelete();
            $table->foreignId('production_line_id')->comment('所属产线 ID')->constrained('mes_production_lines')->restrictOnDelete();
            $table->string('model', 64)->comment('设备型号');
            $table->string('status', 16)->default('idle')->comment('状态：running 运行/idle 待机/fault 故障/repair 维修/offline 离线');
            $table->foreignId('owner_id')->nullable()->comment('设备负责人档案 ID')->constrained('mes_employees')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index(['workshop_id', 'status'], 'mes_equipments_workshop_status_index');
            $table->index(['production_line_id', 'status'], 'mes_equipments_line_status_index');
            $table->index('type', 'mes_equipments_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_equipments');
    }
};
