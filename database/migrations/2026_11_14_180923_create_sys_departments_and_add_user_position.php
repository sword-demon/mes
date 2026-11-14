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
        Schema::create('sys_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('sys_departments')->nullOnDelete();
            $table->string('code', 64)->unique();
            $table->string('name', 64);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('sys_users', function (Blueprint $table) {
            $table->string('username', 64)->nullable()->unique()->after('id');
            $table->foreignId('department_id')->nullable()->after('phone')->constrained('sys_departments')->nullOnDelete();
            $table->string('position', 64)->nullable()->after('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sys_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['username', 'position']);
        });

        Schema::dropIfExists('sys_departments');
    }
};
