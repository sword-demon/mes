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
            $table->id()->comment('主键');
            $table->foreignId('parent_id')->nullable()->comment('上级部门 ID')->constrained('sys_departments')->nullOnDelete();
            $table->string('code', 64)->unique()->comment('部门编码');
            $table->string('name', 64)->comment('部门名称');
            $table->unsignedInteger('sort')->default(0)->comment('排序');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });

        Schema::table('sys_users', function (Blueprint $table) {
            $table->string('username', 64)->nullable()->unique()->after('id')->comment('用户名');
            $table->foreignId('department_id')->nullable()->after('phone')->comment('部门 ID')->constrained('sys_departments')->nullOnDelete();
            $table->string('position', 64)->nullable()->after('department_id')->comment('职位');
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
