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
        Schema::create('prepaid', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('category');
            $table->string('brand');
            $table->string('seller_name');
            $table->integer('seller_price');
            $table->integer('buyer_price');
            $table->string('sku')->unique();
            $table->enum('unlimited', ['ya', 'tidak'])->default('ya');
            $table->integer('stock');
            $table->enum('multi', ['ya', 'tidak'])->default('ya');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prepaid');
    }
};