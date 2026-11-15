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
        Schema::table('mes_inventory_transactions', function (Blueprint $table) {
            $table->foreignId('created_by_id')->nullable()->comment('操作人 ID')->constrained('sys_users')->nullOnDelete();
            $table->index('reference_no', 'mes_inventory_transactions_reference_no_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mes_inventory_transactions', function (Blueprint $table) {
            $table->dropIndex('mes_inventory_transactions_reference_no_index');
            $table->dropConstrainedForeignId('created_by_id');
        });
    }
};
