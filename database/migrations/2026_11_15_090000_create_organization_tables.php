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
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->string('address', 255)->nullable();
            $table->string('contact', 64)->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('mes_workshops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factory_id')->constrained('mes_factories');
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->string('manager', 64)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('mes_production_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('mes_workshops');
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->unsignedInteger('capacity')->default(0);
            $table->string('manager', 64)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('mes_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('mes_workshops');
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->string('leader', 64)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('mes_employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_no', 64)->unique();
            $table->string('name', 64);
            $table->foreignId('department_id')->nullable()->constrained('sys_departments')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('mes_teams')->nullOnDelete();
            $table->string('position', 64)->nullable();
            $table->string('skill_level', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
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
