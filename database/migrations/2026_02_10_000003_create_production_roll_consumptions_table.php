<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trazabilidad: consumo de rollos por orden de producción.
     */
    public function up(): void
    {
        Schema::create('production_roll_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders')->nullOnDelete();
            $table->foreignId('fabric_roll_id')->constrained('fabric_rolls')->cascadeOnDelete();
            $table->decimal('meters_consumed', 20, 6);
            $table->timestamps();

            $table->index(['production_order_id', 'fabric_roll_id'], 'prc_order_roll_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_roll_consumptions');
    }
};

