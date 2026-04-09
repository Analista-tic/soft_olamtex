<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('tax', 8, 2)->default(0);
            $table->decimal('quantity', 15, 4);
            $table->string('description')->nullable();
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('inventory_adjustment_lines', function (Blueprint $table) {
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_lines');
    }
};
