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
        Schema::table('mes_inspection_tasks', function (Blueprint $table) {
            $table->foreignId('route_operation_id')
                ->nullable()
                ->after('process_record_id')
                ->comment('工艺路线工序 ID（OQC 必选，IPQC 取自执行记录）')
                ->constrained('mes_route_operations')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mes_inspection_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('route_operation_id');
        });
    }
};
