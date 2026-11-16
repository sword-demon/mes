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
        Schema::table('sys_login_logs', function (Blueprint $table) {
            $table->string('account', 255)->comment('登录账号')->change();
        });

        Schema::table('sys_operation_logs', function (Blueprint $table) {
            $table->string('object_name', 255)->comment('对象名称')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_operation_logs', function (Blueprint $table) {
            $table->string('object_name', 128)->comment('对象名称')->change();
        });

        Schema::table('sys_login_logs', function (Blueprint $table) {
            $table->string('account', 128)->comment('登录账号')->change();
        });
    }
};
