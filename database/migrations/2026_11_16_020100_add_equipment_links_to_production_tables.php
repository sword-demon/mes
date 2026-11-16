<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mes_production_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('equipment_id')->nullable()->after('team_id')->comment('设备 ID');
            $table->index('equipment_id', 'mes_production_reports_equipment_id_index');
        });

        Schema::table('mes_process_records', function (Blueprint $table) {
            $table->index('equipment_id', 'mes_process_records_equipment_id_index');
        });

        Schema::table('mes_product_sns', function (Blueprint $table) {
            $table->index('equipment_id', 'mes_product_sns_equipment_id_index');
        });

        // SQLite cannot add foreign keys to existing tables; app validation covers
        // the test path while MySQL keeps referential integrity.
        if (DB::getDriverName() !== 'sqlite') {
            $this->nullOrphanEquipmentIds('mes_production_reports');
            $this->nullOrphanEquipmentIds('mes_process_records');
            $this->nullOrphanEquipmentIds('mes_product_sns');

            Schema::table('mes_production_reports', function (Blueprint $table) {
                $table->foreign('equipment_id', 'mes_production_reports_equipment_id_foreign')
                    ->references('id')->on('mes_equipments')->nullOnDelete();
            });

            Schema::table('mes_process_records', function (Blueprint $table) {
                $table->foreign('equipment_id', 'mes_process_records_equipment_id_foreign')
                    ->references('id')->on('mes_equipments')->nullOnDelete();
            });

            Schema::table('mes_product_sns', function (Blueprint $table) {
                $table->foreign('equipment_id', 'mes_product_sns_equipment_id_foreign')
                    ->references('id')->on('mes_equipments')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('mes_product_sns', function (Blueprint $table) {
                $table->dropForeign('mes_product_sns_equipment_id_foreign');
            });

            Schema::table('mes_process_records', function (Blueprint $table) {
                $table->dropForeign('mes_process_records_equipment_id_foreign');
            });

            Schema::table('mes_production_reports', function (Blueprint $table) {
                $table->dropForeign('mes_production_reports_equipment_id_foreign');
            });
        }

        Schema::table('mes_product_sns', function (Blueprint $table) {
            $table->dropIndex('mes_product_sns_equipment_id_index');
        });

        Schema::table('mes_process_records', function (Blueprint $table) {
            $table->dropIndex('mes_process_records_equipment_id_index');
        });

        Schema::table('mes_production_reports', function (Blueprint $table) {
            $table->dropIndex('mes_production_reports_equipment_id_index');
            $table->dropColumn('equipment_id');
        });
    }

    private function nullOrphanEquipmentIds(string $table): void
    {
        DB::table($table)
            ->whereNotNull('equipment_id')
            ->whereNotExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('mes_equipments')
                ->whereColumn('mes_equipments.id', "{$table}.equipment_id"))
            ->update(['equipment_id' => null]);
    }
};
