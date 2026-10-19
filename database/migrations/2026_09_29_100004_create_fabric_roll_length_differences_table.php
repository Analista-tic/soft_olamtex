<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diferencias de metraje detectadas al descargar: el rollo dio más (sobrante) o menos
 * (faltante) de lo registrado. Cada evento es inmutable, queda ligado al descargue que
 * lo originó y a su movimiento de kardex, y espera la revisión de un supervisor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabric_roll_length_differences', function (Blueprint $table) {
            $table->id();
            // Nombres de FK explícitos: los generados superan los 64 caracteres de MySQL.
            $table->foreignId('fabric_roll_id')->constrained('fabric_rolls', 'id', 'frld_roll_fk')->restrictOnDelete();
            // sobrante | faltante
            $table->string('type', 20);
            $table->decimal('meters', 12, 2);
            $table->decimal('quantity_before', 10, 2);
            $table->decimal('quantity_after', 10, 2);
            $table->text('reason');
            $table->foreignId('source_adjustment_id')->nullable()->constrained('fabric_roll_adjustments', 'id', 'frld_source_adjustment_fk')->nullOnDelete();
            $table->foreignId('manual_roll_programming_id')->nullable()->constrained('manual_roll_programmings', 'id', 'frld_manual_programming_fk')->nullOnDelete();
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders', 'id', 'frld_production_order_fk')->nullOnDelete();
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements', 'id', 'frld_inventory_movement_fk')->nullOnDelete();
            // Reservas de otras órdenes liberadas al declarar el faltante: [{source, id, code, meters}]
            $table->json('released_reservations')->nullable();
            $table->foreignId('reverses_id')->nullable()->constrained('fabric_roll_length_differences', 'id', 'frld_reverses_fk')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users', 'id', 'frld_created_by_fk')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users', 'id', 'frld_reviewed_by_fk')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['fabric_roll_id', 'type'], 'frld_roll_type_idx');
            $table->index('reviewed_at', 'frld_reviewed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_roll_length_differences');
    }
};
