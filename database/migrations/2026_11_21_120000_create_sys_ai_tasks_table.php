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
        Schema::create('sys_ai_tasks', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('trace_id', 64)->comment('链路标识');
            $table->string('type', 64)->comment('任务类型');
            $table->string('status', 16)->default('pending')->comment('状态：pending/running/succeeded/failed/cancelled');
            $table->foreignId('actor_id')->nullable()->comment('发起人 ID')->constrained('sys_users')->nullOnDelete();
            $table->string('actor_ip', 45)->nullable()->comment('发起时 IP');
            $table->string('model', 64)->nullable()->comment('模型标识');
            $table->json('input')->nullable()->comment('输入快照');
            $table->json('output')->nullable()->comment('输出快照');
            $table->text('error')->nullable()->comment('错误信息');
            $table->unsignedInteger('prompt_tokens')->nullable()->comment('输入 token 数');
            $table->unsignedInteger('completion_tokens')->nullable()->comment('输出 token 数');
            $table->unsignedInteger('latency_ms')->nullable()->comment('耗时毫秒');
            $table->timestamp('started_at')->nullable()->comment('开始时间');
            $table->timestamp('finished_at')->nullable()->comment('结束时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index('trace_id');
            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_ai_tasks');
    }
};
