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
        Schema::table('sys_users', function (Blueprint $table) {
            $table->foreignId('factory_id')
                ->nullable()
                ->after('department_id')
                ->comment('所属工厂 ID')
                ->constrained('mes_factories')
                ->nullOnDelete();
        });

        Schema::create('sys_user_production_lines', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('user_id')->comment('用户 ID')->constrained('sys_users')->cascadeOnDelete();
            $table->foreignId('production_line_id')->comment('生产产线 ID')->constrained('mes_production_lines')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(['user_id', 'production_line_id'], 'sys_user_production_lines_user_line_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_user_production_lines');

        Schema::table('sys_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('factory_id');
        });
    }
};
