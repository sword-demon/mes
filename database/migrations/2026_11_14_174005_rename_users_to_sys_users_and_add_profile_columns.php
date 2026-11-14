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
        Schema::rename('users', 'sys_users');

        Schema::table('sys_users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email')->comment('手机号');
            $table->boolean('status')->default(true)->after('password')->comment('状态');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'status']);
        });

        Schema::rename('sys_users', 'users');
    }
};
