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
        Schema::create('mes_equipment_alarms', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 40)->unique()->comment('告警编号');
            $table->foreignId('equipment_id')->comment('设备 ID')->constrained('mes_equipments')->restrictOnDelete();
            $table->foreignId('equipment_data_id')->nullable()->comment('触发采样 ID')->constrained('mes_equipment_data')->nullOnDelete();
            $table->string('metric', 16)->comment('指标：temperature/speed/pressure');
            $table->string('level', 16)->comment('等级：info/warning/critical');
            $table->string('status', 16)->default('pending')->comment('状态：pending/confirmed/processing/closed');
            $table->decimal('threshold', 10, 3)->comment('触发阈值');
            $table->decimal('measured_value', 12, 3)->comment('实测值');
            $table->foreignId('confirmed_by_id')->nullable()->comment('确认人 ID')->constrained('sys_users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->comment('确认时间');
            $table->foreignId('handled_by_id')->nullable()->comment('处理人 ID')->constrained('sys_users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable()->comment('处理时间');
            $table->text('handling_note')->nullable()->comment('处理说明');
            $table->dateTime('triggered_at', 6)->comment('告警触发时间（UTC）');
            $table->timestamp('closed_at')->nullable()->comment('关闭时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index(['equipment_id', 'status', 'triggered_at'], 'mes_equipment_alarms_device_status_time_index');
            $table->index(['level', 'status'], 'mes_equipment_alarms_level_status_index');
            $table->index(['status', 'triggered_at'], 'mes_equipment_alarms_status_time_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_equipment_alarms');
    }
};
