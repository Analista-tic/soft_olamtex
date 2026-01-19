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
            // IDs referenciados en parameters - sin foreign keys
            $table->unsignedBigInteger('section_id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('world_id');
            $table->unsignedBigInteger('fabric_base_id');
            $table->unsignedBigInteger('yarn_base_id')->nullable();
            $table->string('reference')->unique();
            $table->float('billing_price')->nullable();
            $table->float('selling_price')->nullable();
            $table->json('sizes')->nullable();
            $table->boolean('woven_product');
            $table->string('description')->nullable();
            $table->json('processes');
            $table->enum('status', ['complete', 'incomplete']);
            $table->softDeletes();
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
