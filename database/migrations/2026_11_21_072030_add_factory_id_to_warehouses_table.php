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
        Schema::table('mes_warehouses', function (Blueprint $table) {
            $table->foreignId('factory_id')
                ->nullable()
                ->after('name')
                ->comment('所属工厂 ID')
                ->constrained('mes_factories')
                ->nullOnDelete();
        });

        // Demo and seed databases already hold one factory; binding existing
        // warehouses to it keeps inventory visible after the scoping ships.
        $factoryId = DB::table('mes_factories')->where('status', true)->orderBy('id')->value('id');

        if ($factoryId !== null) {
            DB::table('mes_warehouses')->whereNull('factory_id')->update(['factory_id' => $factoryId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mes_warehouses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('factory_id');
        });
    }
};
