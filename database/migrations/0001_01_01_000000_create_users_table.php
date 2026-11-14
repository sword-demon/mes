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
        Schema::create('users', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('name')->comment('姓名');
            $table->string('email')->unique()->comment('邮箱');
            $table->timestamp('email_verified_at')->nullable()->comment('邮箱验证时间');
            $table->string('password')->comment('密码哈希');
            $table->rememberToken()->comment('记住登录令牌');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()->comment('邮箱');
            $table->string('token')->comment('重置令牌');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary()->comment('会话标识');
            $table->foreignId('user_id')->nullable()->index()->comment('用户 ID');
            $table->string('ip_address', 45)->nullable()->comment('IP 地址');
            $table->text('user_agent')->nullable()->comment('客户端标识');
            $table->longText('payload')->comment('会话数据');
            $table->integer('last_activity')->index()->comment('最后活跃时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
