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
        Schema::create('mes_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->string('model', 64)->nullable();
            $table->string('specification', 255)->nullable();
            $table->string('category', 64);
            $table->string('unit', 16);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('mes_product_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('mes_products')->restrictOnDelete();
            $table->string('version', 32);
            $table->string('remark', 255)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mes_product_versions');
        Schema::dropIfExists('mes_products');
    }
};
