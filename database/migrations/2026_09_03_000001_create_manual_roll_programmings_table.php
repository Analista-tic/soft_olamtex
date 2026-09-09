<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programación/descargue de rollos por un código de orden escrito a mano, para poder
 * reservar y consumir metros de un rollo mientras el flujo de Órdenes de Producción no
 * está operativo. `production_order_id` es un puente a futuro: siempre null hoy, se
 * podrá vincular a una orden real sin reingresar datos cuando exista. No reemplaza ni
 * depende de fabric_roll_programming_lines/technical_sheets (motor de Corte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_roll_programmings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fabric_roll_id')->constrained('fabric_rolls')->cascadeOnDelete();
            $table->string('manual_order_code', 100);
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders')->nullOnDelete();
            $table->decimal('programmed_meters', 20, 6);
            $table->decimal('consumed_meters', 20, 6)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['fabric_roll_id', 'manual_order_code'], 'manual_roll_programmings_roll_code_unique');
            $table->index('manual_order_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_roll_programmings');
    }
};
