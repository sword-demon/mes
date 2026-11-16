<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mes_production_orders', function (Blueprint $table): void {
            $table->index(['created_at', 'id'], 'mes_production_orders_created_at_id_index');
        });

        Schema::table('mes_production_reports', function (Blueprint $table): void {
            $table->index(['reported_at', 'id'], 'mes_production_reports_reported_at_id_index');
        });

        Schema::table('mes_equipment_alarms', function (Blueprint $table): void {
            $table->index(['triggered_at', 'id'], 'mes_equipment_alarms_triggered_at_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('mes_production_orders', function (Blueprint $table): void {
            $table->dropIndex('mes_production_orders_created_at_id_index');
        });

        Schema::table('mes_production_reports', function (Blueprint $table): void {
            $table->dropIndex('mes_production_reports_reported_at_id_index');
        });

        Schema::table('mes_equipment_alarms', function (Blueprint $table): void {
            $table->dropIndex('mes_equipment_alarms_triggered_at_id_index');
        });
    }
};
