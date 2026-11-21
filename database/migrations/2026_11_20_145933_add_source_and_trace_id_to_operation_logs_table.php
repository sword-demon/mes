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
        Schema::table('sys_operation_logs', function (Blueprint $table) {
            $table->string('source', 16)
                ->default('human')
                ->after('object_name')
                ->comment('操作来源：human/ai/system');
            $table->string('trace_id', 64)
                ->nullable()
                ->after('source')
                ->comment('调用链路标识');

            $table->index('source');
            $table->index('trace_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_operation_logs', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropIndex(['trace_id']);
            $table->dropColumn(['source', 'trace_id']);
        });
    }
};
