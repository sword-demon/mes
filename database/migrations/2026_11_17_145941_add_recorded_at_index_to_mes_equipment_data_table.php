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
        Schema::table('mes_equipment_data', function (Blueprint $table): void {
            $table->index(['recorded_at', 'id'], 'mes_equipment_data_recorded_at_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mes_equipment_data', function (Blueprint $table): void {
            $table->dropIndex('mes_equipment_data_recorded_at_id_index');
        });
    }
};
