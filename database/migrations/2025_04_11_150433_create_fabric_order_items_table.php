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
        Schema::create('fabric_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fabric_order_id')
                ->constrained()
                ->onDelete('cascade'); // Permitir eliminación lógica en cascada
            $table->string('fabric');
            $table->foreignId('base_fabric_id')->constrained();
            $table->string('provider_color');
            $table->string('provider_color_code');
            $table->decimal('quantity', 10, 2);
            $table->decimal('estimated_width', 10, 2);
            $table->decimal('price', 10, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fabric_order_items');
    }
};
