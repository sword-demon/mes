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
            $table->id()->comment('主键');
            $table->string('code', 64)->unique()->comment('角色编码');
            $table->string('name', 64)->comment('角色名称');
            $table->string('description')->nullable()->comment('角色描述');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });

        Schema::create('sys_permissions', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 128)->unique()->comment('权限编码');
            $table->string('name', 64)->comment('权限名称');
            $table->string('type', 16)->index()->comment('权限类型');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });

        Schema::create('sys_menus', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('parent_id')->nullable()->comment('父菜单 ID')->constrained('sys_menus')->cascadeOnDelete();
            $table->foreignId('permission_id')->nullable()->comment('权限 ID')->constrained('sys_permissions')->nullOnDelete();
            $table->string('name', 64)->comment('菜单名称');
            $table->string('path', 128)->nullable()->comment('路由路径');
            $table->string('icon', 64)->nullable()->comment('图标');
            $table->unsignedInteger('sort')->default(0)->comment('排序');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });

        Schema::create('sys_user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->comment('用户 ID')->constrained('sys_users')->cascadeOnDelete();
            $table->foreignId('role_id')->comment('角色 ID')->constrained('sys_roles')->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('sys_role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->comment('角色 ID')->constrained('sys_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->comment('权限 ID')->constrained('sys_permissions')->cascadeOnDelete();
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
