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
        Schema::create('mes_equipment_data', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('equipment_id')->comment('设备 ID')->constrained('mes_equipments')->restrictOnDelete();
            $table->string('message_type', 16)->comment('消息类型：status/data/alarm');
            $table->dateTime('recorded_at', 6)->comment('设备采集时间（UTC）');
            $table->string('status', 16)->comment('设备运行状态');
            $table->decimal('temperature', 10, 3)->comment('温度');
            $table->unsignedInteger('speed')->comment('转速');
            $table->decimal('pressure', 10, 3)->comment('压力');
            $table->unsignedInteger('output')->comment('设备累计产量');
            $table->json('alarm')->nullable()->comment('告警原始信息：code/message/level');
            $table->timestamp('created_at', 6)->nullable()->comment('创建时间');
            $table->timestamp('updated_at', 6)->nullable()->comment('更新时间');

            $table->unique(['equipment_id', 'recorded_at', 'message_type'], 'mes_equipment_data_sample_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_equipment_data');
    }
};
