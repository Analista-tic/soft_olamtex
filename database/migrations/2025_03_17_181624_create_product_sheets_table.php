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
        Schema::create('product_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained();
            $table->foreignId('group_id')->constrained();
            $table->foreignId('brand_id')->constrained();
            $table->foreignId('world_id')->constrained();
            $table->foreignId('base_id')->constrained();
            $table->string('reference')->unique();
            $table->float('billing_price')->nullable();
            $table->float('selling_price')->nullable();
            $table->json('sizes')->nullable();
            $table->boolean('woven_product');
            $table->string('description');
            $table->json('processes');
            $table->enum('status', ['completed', 'incomplete']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sheets');
    }
};
