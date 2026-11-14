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
        Schema::create('sys_roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 64);
            $table->string('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('sys_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 128)->unique();
            $table->string('name', 64);
            $table->string('type', 16)->index();
            $table->timestamps();
        });

        Schema::create('sys_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('sys_menus')->cascadeOnDelete();
            $table->foreignId('permission_id')->nullable()->constrained('sys_permissions')->nullOnDelete();
            $table->string('name', 64);
            $table->string('path', 128)->nullable();
            $table->string('icon', 64)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('sys_user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('sys_users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('sys_roles')->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('sys_role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('sys_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('sys_permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_role_permissions');
        Schema::dropIfExists('sys_user_roles');
        Schema::dropIfExists('sys_menus');
        Schema::dropIfExists('sys_permissions');
        Schema::dropIfExists('sys_roles');
    }
};
