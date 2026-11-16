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
        Schema::create('sys_operation_logs', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('user_id')->nullable()->comment('操作人 ID')->constrained('sys_users')->nullOnDelete();
            $table->string('user_name', 64)->comment('操作人姓名快照');
            $table->string('action', 64)->index()->comment('动作编码');
            $table->string('action_name', 64)->comment('动作名称');
            $table->string('object_type', 32)->comment('对象类型');
            $table->unsignedBigInteger('object_id')->nullable()->comment('对象 ID');
            $table->string('object_name', 128)->comment('对象名称');
            $table->string('summary', 500)->comment('操作摘要');
            $table->json('changes')->nullable()->comment('变更前后摘要');
            $table->string('ip', 45)->comment('操作 IP');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->index('created_at');
        });

        Schema::create('sys_login_logs', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('user_id')->nullable()->comment('用户 ID')->constrained('sys_users')->nullOnDelete();
            $table->string('account', 128)->comment('登录账号');
            $table->string('ip', 45)->comment('登录 IP');
            $table->boolean('success')->comment('是否成功');
            $table->string('message', 255)->comment('登录结果说明');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->index(['account', 'created_at']);
            $table->index(['success', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_login_logs');
        Schema::dropIfExists('sys_operation_logs');
    }
};
