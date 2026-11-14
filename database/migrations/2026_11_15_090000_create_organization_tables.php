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
        Schema::create('mes_factories', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('code', 64)->unique()->comment('工厂编码');
            $table->string('name', 128)->comment('工厂名称');
            $table->string('address', 255)->nullable()->comment('地址');
            $table->string('contact', 64)->nullable()->comment('联系人');
            $table->string('phone', 32)->nullable()->comment('联系电话');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });

        Schema::create('mes_workshops', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('factory_id')->comment('所属工厂 ID')->constrained('mes_factories');
            $table->string('code', 64)->unique()->comment('车间编码');
            $table->string('name', 128)->comment('车间名称');
            $table->string('manager', 64)->nullable()->comment('负责人');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });

        Schema::create('mes_production_lines', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('workshop_id')->comment('所属车间 ID')->constrained('mes_workshops');
            $table->string('code', 64)->unique()->comment('产线编码');
            $table->string('name', 128)->comment('产线名称');
            $table->unsignedInteger('capacity')->default(0)->comment('日产能');
            $table->string('manager', 64)->nullable()->comment('负责人');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });

        Schema::create('mes_teams', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->foreignId('workshop_id')->comment('所属车间 ID')->constrained('mes_workshops');
            $table->string('code', 64)->unique()->comment('班组编码');
            $table->string('name', 128)->comment('班组名称');
            $table->string('leader', 64)->nullable()->comment('班组长');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });

        Schema::create('mes_employees', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('employee_no', 64)->unique()->comment('工号');
            $table->string('name', 64)->comment('姓名');
            $table->foreignId('department_id')->nullable()->comment('部门 ID')->constrained('sys_departments')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->comment('班组 ID')->constrained('mes_teams')->nullOnDelete();
            $table->string('position', 64)->nullable()->comment('职位');
            $table->string('skill_level', 32)->nullable()->comment('技能等级');
            $table->string('phone', 32)->nullable()->comment('手机号');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_employees');
        Schema::dropIfExists('mes_teams');
        Schema::dropIfExists('mes_production_lines');
        Schema::dropIfExists('mes_workshops');
        Schema::dropIfExists('mes_factories');
    }
};
