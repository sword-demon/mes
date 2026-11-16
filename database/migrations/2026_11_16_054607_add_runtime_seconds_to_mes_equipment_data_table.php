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
        Schema::table('mes_equipment_data', function (Blueprint $table) {
            $table->unsignedInteger('runtime_seconds')->nullable()->comment('设备模拟进程本次启动以来累计运行秒数，空值表示未采集');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mes_equipment_data', function (Blueprint $table) {
            $table->dropColumn('runtime_seconds');
        });
    }
};
