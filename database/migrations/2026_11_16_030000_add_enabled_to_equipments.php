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
        Schema::table('mes_equipments', function (Blueprint $table) {
            $table->boolean('enabled')->default(true)->after('status')->comment('是否启用');
            $table->index('enabled', 'mes_equipments_enabled_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mes_equipments', function (Blueprint $table) {
            $table->dropIndex('mes_equipments_enabled_index');
            $table->dropColumn('enabled');
        });
    }
};
